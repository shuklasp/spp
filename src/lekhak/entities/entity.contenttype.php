<?php
namespace App\Lekhak\Entities;

use SPPMod\SPPDB\SPPEntity;

/**
 * Class ContentType
 * Defines a content type (bundle) for nodes.
 */
class ContentType extends SPPEntity
{
    protected string $table = 'content_types';

    public static function count()
    {
        static::ensureSchema();
        return parent::count();
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
        $table = \SPPMod\SPPDB\SPPDB::sppTable('content_types');
        $isSqlite = $db->getDriver() === 'sqlite';

        if ($isSqlite) {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                label VARCHAR(255),
                description TEXT,
                storage_strategy VARCHAR(20),
                help_text TEXT
            )");
        } else {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                label VARCHAR(255),
                description TEXT,
                storage_strategy VARCHAR(20),
                help_text TEXT
            )");
        }

        foreach (static::schemaColumns() as $column => $type) {
            if (!$db->columnExists($table, $column)) {
                $db->exec("ALTER TABLE {$table} ADD {$column} {$type}");
            }
        }
    }

    protected static function schemaColumns(): array
    {
        return [
            'name' => 'VARCHAR(50)',
            'label' => 'VARCHAR(255)',
            'description' => 'TEXT',
            'storage_strategy' => 'VARCHAR(20)',
            'help_text' => 'TEXT'
        ];
    }

    public function define_attributes()
    {
        return [
            'name' => 'varchar(50)', // Machine name
            'label' => 'varchar(255)',
            'description' => 'text',
            'storage_strategy' => 'varchar(20)', // flat or dynamic
            'help_text' => 'text'
        ];
    }

    public function field_metadata()
    {
        return [
            'name' => [
                'label' => 'Machine Name',
                'help' => 'A unique identifier using only lowercase letters, numbers, and underscores.'
            ],
            'label' => [
                'label' => 'Display Name',
                'help' => 'The human-readable name for this content type (e.g. Article, Page).'
            ],
            'description' => [
                'label' => 'Description',
                'help' => 'Explain what this content type is used for.'
            ],
            'storage_strategy' => [
                'label' => 'Storage Strategy',
                'type' => 'radio',
                'options' => [
                    'flat' => 'Flat Table (Recommended)',
                    'dynamic' => 'Dynamic Properties (JSON)'
                ],
                'help' => 'Choose how fields are stored in the database.'
            ],
            'help_text' => [
                'label' => 'Default Help Text',
                'help' => 'A message that will be shown to content creators by default.'
            ]
        ];
    }
}
