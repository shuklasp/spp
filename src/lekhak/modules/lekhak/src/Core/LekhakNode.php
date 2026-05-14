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
                if ($user) $this->author_id = $user->id;
            }
            if (!$this->status) {
                $this->status = 'published';
            }
        }
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
            'changed' => 'datetime'
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
