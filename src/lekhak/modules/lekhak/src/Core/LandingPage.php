<?php
namespace SPPMod\Lekhak\Core;

/**
 * Class LandingPage
 * A specialized LekhakNode that serves as a landing page with blocks.
 */
class LandingPage extends LekhakNode
{
    protected string $table = 'landing_pages';
    
    public function after_creation()
    {
        parent::after_creation();
        $this->bundle = 'landing_page';
    }

    public function define_attributes()
    {
        $attrs = parent::define_attributes();
        $attrs['is_default'] = 'tinyint(1)';
        $attrs['layout_id'] = 'varchar(50)';
        return $attrs;
    }

    /**
     * Get all blocks for this page.
     */
    public function getBlocks(): array
    {
        if (!$this->id) return [];
        return LandingBlock::find_all(['page_id' => $this->id], 'weight ASC');
    }

    /**
     * Set this page as the default homepage.
     */
    public function setAsDefault(): void
    {
        static::ensureSchema();
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable($this->table);
        $db->execute_query("UPDATE {$table} SET is_default = 0");
        $this->is_default = 1;
        $this->save();
    }

    /**
     * Find the default landing page.
     */
    public static function getDefault(): ?LandingPage
    {
        static::ensureSchema();
        return static::find_one(['is_default' => 1]);
    }

    public static function find_one(array $conditions = [])
    {
        static::ensureSchema();
        return parent::find_one($conditions);
    }

    public static function find_all(array $conditions = [], string $sort = null, int $limit = null)
    {
        static::ensureSchema();
        return parent::find_all($conditions, $sort, $limit);
    }

    public static function ensureSchema(): void
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $table = \SPPMod\SPPDB\SPPDB::sppTable('landing_pages');
        $blocksTable = \SPPMod\SPPDB\SPPDB::sppTable('landing_blocks');
        $isSqlite = $db->getDriver() === 'sqlite';

        if ($isSqlite) {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                alias VARCHAR(255),
                body TEXT,
                bundle VARCHAR(50) NOT NULL DEFAULT 'landing_page',
                author_id INTEGER,
                status VARCHAR(20),
                langcode VARCHAR(10),
                translation_id INTEGER,
                created DATETIME,
                changed DATETIME,
                fields_data TEXT,
                is_default INTEGER DEFAULT 0,
                layout_id VARCHAR(50)
            )");
            $columns = [
                'alias' => 'VARCHAR(255)',
                'body' => 'TEXT',
                'bundle' => "VARCHAR(50) DEFAULT 'landing_page'",
                'author_id' => 'INTEGER',
                'status' => 'VARCHAR(20)',
                'langcode' => 'VARCHAR(10)',
                'translation_id' => 'INTEGER',
                'created' => 'DATETIME',
                'changed' => 'DATETIME',
                'fields_data' => 'TEXT',
                'is_default' => 'INTEGER DEFAULT 0',
                'layout_id' => 'VARCHAR(50)'
            ];
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$blocksTable} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                page_id INTEGER DEFAULT 0,
                block_type VARCHAR(50),
                data TEXT,
                weight INTEGER DEFAULT 0,
                region VARCHAR(50),
                created DATETIME
            )");
        } else {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                alias VARCHAR(255),
                body LONGTEXT,
                bundle VARCHAR(50) NOT NULL DEFAULT 'landing_page',
                author_id BIGINT,
                status VARCHAR(20),
                langcode VARCHAR(10),
                translation_id BIGINT,
                created DATETIME,
                changed DATETIME,
                fields_data LONGTEXT,
                is_default TINYINT(1) DEFAULT 0,
                layout_id VARCHAR(50)
            )");
            $columns = [
                'alias' => 'VARCHAR(255)',
                'body' => 'LONGTEXT',
                'bundle' => "VARCHAR(50) DEFAULT 'landing_page'",
                'author_id' => 'BIGINT',
                'status' => 'VARCHAR(20)',
                'langcode' => 'VARCHAR(10)',
                'translation_id' => 'BIGINT',
                'created' => 'DATETIME',
                'changed' => 'DATETIME',
                'fields_data' => 'LONGTEXT',
                'is_default' => 'TINYINT(1) DEFAULT 0',
                'layout_id' => 'VARCHAR(50)'
            ];
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$blocksTable} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_id BIGINT DEFAULT 0,
                block_type VARCHAR(50),
                data LONGTEXT,
                weight INT DEFAULT 0,
                region VARCHAR(50),
                created DATETIME
            )");
        }

        foreach ($columns as $column => $type) {
            if (!$db->columnExists($table, $column)) {
                $db->exec("ALTER TABLE {$table} ADD {$column} {$type}");
            }
        }
    }

    public function field_metadata()
    {
        $meta = parent::field_metadata();
        $meta = array_merge($meta, [
            'title' => [
                'label' => 'Page Title',
                'placeholder' => 'e.g. Summer Marketing Campaign',
                'help' => 'Enter a descriptive title for this landing page.',
                'validations' => [
                    ['type' => 'required', 'message' => 'Page title is required.']
                ]
            ],
            'alias' => [
                'label' => 'URL Alias',
                'placeholder' => 'e.g. summer-sale',
                'help' => 'The unique URL path for this page (e.g. /lekhak/summer-sale).',
                'validations' => [
                    ['type' => 'required', 'message' => 'URL alias is required.'],
                    ['type' => 'unique', 'table' => 'landing_pages', 'column' => 'alias', 'message' => 'This URL alias is already in use.']
                ]
            ],
            'is_default' => [
                'label' => 'Set as Homepage',
                'type' => 'toggle',
                'help' => 'If enabled, this landing page will become the main entry point for your site.'
            ],
            'layout_id' => [
                'label' => 'Layout Template',
                'type' => 'select',
                'options' => [
                    'standard' => 'Standard Glass (Full width)',
                    'sidebar' => 'Content with Sidebar',
                    'minimal' => 'Minimal / Focused'
                ]
            ],
            // Internal fields that should be hidden from the form
            'author_id' => ['type' => 'hidden'],
            'bundle' => ['type' => 'hidden'],
            'created' => ['type' => 'hidden'],
            'changed' => ['type' => 'hidden'],
            'translation_id' => ['type' => 'hidden']
        ]);
        return $meta;
    }
}
