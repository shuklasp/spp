<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Testentity;

/**
 * Auto-generated Test for TestEntity (Parikshak)
 * Generation Date: 2026-07-11 13:08:12
 */
class TestEntityAutoTest
{
    public static function run()
    {
        echo "Running evaluator for TestEntity... ";
        try {
            $entity = new TestEntity();
            $data = array (
  'id' => 450886,
  'username' => 'PARIKSHAK_FUZZ_2a520',
  'email' => 'PARIKSHAK_FUZZ_20d52',
  'password_hash' => 'PARIKSHAK_FUZZ_6eb1c',
  'password' => 'PARIKSHAK_FUZZ_12a94',
  'role_id' => 112723,
  'status' => 'PARIKSHAK_FUZZ_fa40d',
  'created_at' => '2026-07-11 13:08:12',
  'updated_at' => '2026-07-11 13:08:12',
  'name' => 'PARIKSHAK_FUZZ_78e60',
  'test1' => 593774,
  'dob' => '2026-07-11 13:08:12',
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
