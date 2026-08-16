<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Testentity;

/**
 * Auto-generated Test for TestEntity (Parikshak)
 * Generation Date: 2026-07-18 08:36:50
 */
class TestEntityAutoTest
{
    public static function run()
    {
        echo "Running evaluator for TestEntity... ";
        try {
            $entity = new TestEntity();
            $data = array (
  'id' => 437349,
  'username' => 'PARIKSHAK_FUZZ_f8829',
  'email' => 'PARIKSHAK_FUZZ_2f46b',
  'password_hash' => 'PARIKSHAK_FUZZ_dcad5',
  'password' => 'PARIKSHAK_FUZZ_55c17',
  'role_id' => 254505,
  'status' => 'PARIKSHAK_FUZZ_e0120',
  'created_at' => '2026-07-18 08:36:50',
  'updated_at' => '2026-07-18 08:36:50',
  'name' => 'PARIKSHAK_FUZZ_e3f4f',
  'test1' => 757810,
  'dob' => '2026-07-18 08:36:50',
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
