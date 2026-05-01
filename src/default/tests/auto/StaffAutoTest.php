<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Staff;

/**
 * Auto-generated Test for Staff (Parikshak)
 * Generation Date: 2026-04-26 15:40:19
 */
class StaffAutoTest
{
    public static function run()
    {
        echo "Running evaluator for Staff... ";
        try {
            $entity = new Staff();
            $data = array (
  'id' => 659200,
  'name' => 'PARIKSHAK_FUZZ_8bf01',
  'department' => 'PARIKSHAK_FUZZ_0f3fb',
  'created_at' => '2026-04-26 15:40:19',
  'parent_id' => 'PARIKSHAK_FUZZ_5738a',
);
            foreach ($data as $k => $v) $entity->set($k, $v);
            $id = $entity->save();
            if (!$id) throw new \Exception('Failed to save entity');
            echo "OK (ID: $id)\n";
        } catch (\Exception $e) {
            echo "FAILED: " . $e->getMessage() . "\n";
        }
    }
}
