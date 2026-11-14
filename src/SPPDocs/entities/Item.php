<?php
namespace App\SPPDocs\Entities;

use SPPMod\SPPDB\SPPEntity;

/**
 * ============================================================================
 * Item Entity — SPPDocs
 * ============================================================================
 *
 * HOW ENTITIES WORK:
 * Entities map to database tables via the SPP ORM (SPPDB).
 * They can be exposed as REST API endpoints automatically.
 *
 * TABLE: SPPDocs_items (uses the app's table prefix)
 *
 * CREATE TABLE:
 *   CREATE TABLE SPPDocs_items (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     name VARCHAR(255) NOT NULL,
 *     status VARCHAR(50) DEFAULT 'active',
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 * USAGE:
 *   $items = Item::findAll();
 *   $item = Item::find_one(['id' => 1]);
 *   $item = new Item(['name' => 'Test', 'status' => 'active']);
 *   $item->save();
 *
 * API EXPOSURE:
 *   Set 'enable_api' => true in getMetadata() to expose via SPPAPI.
 * ============================================================================
 */
class Item extends SPPEntity
{
    protected string $table = 'SPPDocs_items';

    public function define_attributes(): array
    {
        return [
            'id' => 'int auto_increment primary key',
            'name' => 'varchar(255) not null',
            'status' => 'varchar(50) default active',
            'created_at' => 'timestamp',
        ];
    }

    public static function getTableName(): string
    {
        return 'SPPDocs_items';
    }

    public static function getMetadata(string $key, $default = null)
    {
        $meta = [
            'table' => self::getTableName(),
            'id_field' => 'id',
            'sequence' => self::getTableName() . '_seq',
            'key_type' => 'int',
            'enable_api' => true,
            'fields' => ['id', 'name', 'status', 'created_at'],
            'searchable' => ['name'],
            'attributes' => [
                'id' => 'int auto_increment primary key',
                'name' => 'varchar(255) not null',
                'status' => 'varchar(50) default active',
                'created_at' => 'timestamp',
            ],
        ];
        return $meta[$key] ?? parent::getMetadata($key, $default);
    }
}