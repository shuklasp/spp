<?php
namespace App\FinalTest\Entities;

/**
 * ============================================================================
 * Item Entity — FinalTest
 * ============================================================================
 *
 * HOW ENTITIES WORK:
 * Entities map to database tables via the SPP ORM (SPPDB).
 * They can be exposed as REST API endpoints automatically.
 *
 * TABLE: FinalTest_items (uses the app's table prefix)
 *
 * CREATE TABLE:
 *   CREATE TABLE FinalTest_items (
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
class Item
{
    public static function getTableName(): string
    {
        return 'FinalTest_items';
    }

    public static function getMetadata(string $key = '')
    {
        $meta = [
            'table' => self::getTableName(),
            'enable_api' => true,  // Expose via REST API
            'fields' => ['id', 'name', 'status', 'created_at'],
            'searchable' => ['name'],
        ];
        return $key ? ($meta[$key] ?? null) : $meta;
    }
}