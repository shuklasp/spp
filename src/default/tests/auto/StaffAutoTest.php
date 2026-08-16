<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Staff;

/**
 * Auto-generated Test for Staff (Parikshak)
 * Generation Date: 2026-07-18 08:36:46
 */
class StaffAutoTest
{
    public static function run()
    {
        echo "Running evaluator for Staff... ";
        try {
            $entity = new Staff();
            $data = array (
  'id' => 926853,
  'name' => 'PARIKSHAK_FUZZ_f5545',
  'department' => 'PARIKSHAK_FUZZ_50b4b',
  'created_at' => '2026-07-18 08:36:46',
  'parent_id' => 'PARIKSHAK_FUZZ_4bea7',
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
