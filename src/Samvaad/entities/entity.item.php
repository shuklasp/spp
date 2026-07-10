<?php
namespace App\Samvaad\Entities;

/**
 * ============================================================================
 * Item Entity — Samvaad
 * ============================================================================
 *
 * HOW ENTITIES WORK:
 * Entities map to database tables via the SPP ORM (SPPDB).
 * They can be exposed as REST API endpoints automatically.
 *
 * TABLE: Samvaad_items (uses the app's table prefix)
 *
 * CREATE TABLE:
 *   CREATE TABLE Samvaad_items (
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
 *
 * NATIVE WORKFLOW API:
 *   Extending SPPEntity provides native workflow capabilities:
 *     $item->getWorkflowState();
 *     $item->canTransition('start');
 *     $item->applyTransition('start', null, 'Automated transition');
 *     $item->getWorkflowHistory();
 * ============================================================================
 */
class Item extends \SPPMod\SPPDB\SPPEntity
{
    public static function getTableName(): string
    {
        return 'Samvaad_items';
    }

    public static function getMetadata(string $key = '', $default = null)
    {
        $meta = [
            'table' => self::getTableName(),
            'enable_api' => true,  // Expose via REST API
            'fields' => ['id', 'name', 'status', 'created_at'],
            'searchable' => ['name'],
        ];
        return $key ? ($meta[$key] ?? $default) : $meta;
    }
}