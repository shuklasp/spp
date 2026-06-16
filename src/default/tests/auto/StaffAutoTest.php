<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Staff;

/**
 * Auto-generated Test for Staff (Parikshak)
 * Generation Date: 2026-06-14 07:11:12
 */
class StaffAutoTest
{
    public static function run()
    {
        echo "Running evaluator for Staff... ";
        try {
            $entity = new Staff();
            $data = array (
  'id' => 407044,
  'name' => 'PARIKSHAK_FUZZ_ecae2',
  'department' => 'PARIKSHAK_FUZZ_0b69e',
  'created_at' => '2026-06-14 07:11:12',
  'parent_id' => 'PARIKSHAK_FUZZ_c68e3',
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
