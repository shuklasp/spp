<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Staff;

/**
 * Auto-generated Test for Staff (Parikshak)
 * Generation Date: 2026-09-04 15:51:28
 */
class StaffAutoTest
{
    public static function run()
    {
        echo "Running evaluator for Staff... ";
        try {
            $entity = new Staff();
            $data = array (
  'id' => 90363,
  'name' => 'PARIKSHAK_FUZZ_00692',
  'department' => 'PARIKSHAK_FUZZ_a88d6',
  'created_at' => '2026-09-04 15:51:28',
  'parent_id' => 'PARIKSHAK_FUZZ_174e1',
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
