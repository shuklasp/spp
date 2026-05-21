<?php
namespace SPPMod\Lekhak\Core;

use SPPMod\SPPEntity\SPPEntity;

/**
 * Class LekhakNode
 * The base entity for all CMS content.
 */
class LekhakNode extends SPPEntity
{
    protected ?string $storage_strategy = 'flat';
    protected string $table = 'nodes';
    protected ?string $sequence = 'nodes_seq';

    public function after_creation()
    {
        $this->storage_strategy = static::getMetadata('storage_strategy', 'flat');
        // Ensure bundle is set
        if (!$this->bundle) {
            $this->bundle = 'page';
        }
    }

    public function before_save()
    {
        parent::before_save();
        $now = date('Y-m-d H:i:s');
        $this->changed = $now;
        if (!$this->id) {
            $this->created = $now;
            if (!$this->author_id && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
                $user = \SPPMod\SPPAuth\SPPAuth::user();
                $userId = $user->id ?? (method_exists($user, 'getId') ? $user->getId() : null);
                if (is_numeric($userId)) {
                    $this->author_id = (int) $userId;
                }
            }
            if (!$this->status) {
                $this->status = 'published';
            }
        }
    }

    public function after_save()
    {
        parent::after_save();
        // Automatically write default grant if node_access table exists
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_access');
        if ($db->tableExists($table)) {
            $db->exec_squery("DELETE FROM %tab% WHERE nid = ? AND gid = ? AND realm = ?", $table, [$this->id, 0, 'all']);
            $db->exec_squery("INSERT INTO %tab% (nid, gid, realm, grant_view, grant_update, grant_delete) VALUES (?, ?, ?, ?, ?, ?)",
                $table, [$this->id, 0, 'all', 1, 0, 0]);
        }
    }

    /**
     * Transition the publishing status of this node utilizing the WorkflowManager.
     */
    public function transitionStatus(string $newStatus, $user = null): bool
    {
        if (class_exists('\\SPP\\Core\\WorkflowManager')) {
            if (\SPP\Core\WorkflowManager::validateTransition($this, $this->status ?? 'draft', $newStatus, $user)) {
                $this->status = $newStatus;
                $this->save();
                return true;
            }
        } else {
            $this->status = $newStatus;
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * Add a taxonomy term to this node.
     */
    public function addTerm(int $tid)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_terms');
        if (!$db->tableExists($table)) {
            $db->exec_squery("CREATE TABLE %tab% (
                nid BIGINT,
                tid BIGINT,
                PRIMARY KEY (nid, tid)
            )", $table);
        }
        $db->exec_squery("DELETE FROM %tab% WHERE nid = ? AND tid = ?", $table, [$this->id, $tid]);
        $db->exec_squery("INSERT INTO %tab% (nid, tid) VALUES (?, ?)", $table, [$this->id, $tid]);
    }

    /**
     * Get all terms attached to this node.
     */
    public function getTerms(): array
    {
        if (!$this->id) return [];
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_terms');
        if (!$db->tableExists($table)) return [];
        
        $res = $db->exec_squery("SELECT tid FROM %tab% WHERE nid = ?", $table, [$this->id]);
        $terms = [];
        foreach ($res as $row) {
            $term = \App\Lekhak\Entities\Term::find_one(['id' => $row['tid']]);
            if ($term) {
                $terms[] = $term;
            }
        }
        return $terms;
    }

    /**
     * Check if a given user has access to a specific operation on this node.
     */
    public function checkAccess(string $op, $user = null): bool
    {
        if ($user === null && class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
        }
        
        // Admin user has all access
        if ($user && isset($user->roles) && in_array('administrator', $user->roles)) {
            return true;
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('node_access');
        if (!$db->tableExists($table)) {
            return true; // Default fallback to open access if no matrix is setup
        }

        $gids = [0]; // "all" anonymous/authenticated group
        if ($user) {
            $gids[] = (int)$user->id;
            if (isset($user->groups) && is_array($user->groups)) {
                foreach ($user->groups as $grp) {
                    $gids[] = (int)$grp;
                }
            }
        }

        $opCol = 'grant_view';
        if ($op === 'update') $opCol = 'grant_update';
        if ($op === 'delete') $opCol = 'grant_delete';

        $placeholders = implode(',', array_fill(0, count($gids), '?'));
        $sql = "SELECT COUNT(*) as cnt FROM %tab% WHERE nid = ? AND {$opCol} = 1 AND gid IN ({$placeholders})";
        
        $params = array_merge([$this->id], $gids);
        $res = $db->exec_squery($sql, $table, $params);
        
        return (int)($res[0]['cnt'] ?? 0) > 0;
    }

    /**
     * Override save to use the strategy driver.
     */
    public function save($strategy_override = true)
    {
        if ($strategy_override && $this->storage_strategy === 'dynamic') {
            $orchestrator = new StorageOrchestrator();
            $orchestrator->ensureSchema(static::class);
            return $this->getStorageDriver()->save($this);
        }
        return parent::save();
    }

    /**
     * Override load to use the strategy driver.
     */
    public function load($id)
    {
        if ($this->storage_strategy === 'dynamic') {
            return $this->getStorageDriver()->load($this, $id);
        }
        return parent::load($id);
    }

    protected function getStorageDriver()
    {
        if ($this->storage_strategy === 'dynamic') {
            // Check if we have a custom driver for this bundle
            $bundleDriver = "\\SPPMod\\Lekhak\\Storage\\" . ucfirst($this->bundle) . "StorageDriver";
            if (class_exists($bundleDriver)) {
                return new $bundleDriver();
            }
            return new \SPPMod\Lekhak\Storage\DynamicStorageDriver();
        }
        return new \SPPMod\Lekhak\Storage\FlatStorageDriver();
    }

    /**
     * Define default CMS attributes.
     */
    public function define_attributes()
    {
        return [
            'title' => 'varchar(255)',
            'alias' => 'varchar(255)',
            'bundle' => 'varchar(50)',
            'body' => 'longtext',
            'author_id' => 'bigint',
            'status' => 'varchar(20)',
            'langcode' => 'varchar(10)',
            'translation_id' => 'bigint',
            'created' => 'datetime',
            'changed' => 'datetime',
            'fields_data' => 'longtext'
        ];
    }
    
    /**
     * Define metadata for form building and UI hints.
     */
    public function field_metadata()
    {
        return [
            'bundle' => [
                'label' => 'Content Type',
                'type' => 'select',
                'source' => [
                    'table' => 'content_types',
                    'value_field' => 'name',
                    'label_field' => 'label'
                ],
                'help' => 'The fundamental type of this content (e.g. Page, Article).'
            ],
            'status' => [
                'label' => 'Publishing Status',
                'type' => 'select',
                'options' => [
                    'published' => 'Published (Live on site)',
                    'draft' => 'Draft (Not yet visible)',
                    'archived' => 'Archived (Removed from site)'
                ],
                'help' => 'Controls whether this content is visible to the public.'
            ],
            'langcode' => [
                'label' => 'Language',
                'type' => 'select',
                'options' => [
                    'en' => 'English (International)',
                    'hi' => 'Hindi (Regional)',
                    'es' => 'Spanish',
                    'fr' => 'French'
                ],
                'help' => 'The language this specific version of content is written in.'
            ],
            'title' => [
                'label' => 'Title',
                'help' => 'The main heading that identifies this content to users.'
            ],
            'alias' => [
                'label' => 'URL Alias',
                'help' => 'A custom URL path for this page (e.g. "our-history"). Leave blank to auto-generate.'
            ],
            'body' => [
                'label' => 'Main Content',
                'help' => 'The primary text, images, and formatting for this page.'
            ]
        ];
    }
}
