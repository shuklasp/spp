<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Testentity;

/**
 * Auto-generated Test for TestEntity (Parikshak)
 * Generation Date: 2026-06-14 07:11:12
 */
class TestEntityAutoTest
{
    public static function run()
    {
        echo "Running evaluator for TestEntity... ";
        try {
            $entity = new TestEntity();
            $data = array (
  'id' => 320536,
  'username' => 'PARIKSHAK_FUZZ_7fb60',
  'email' => 'PARIKSHAK_FUZZ_b0f3d',
  'password_hash' => 'PARIKSHAK_FUZZ_27754',
  'password' => 'PARIKSHAK_FUZZ_8a9f3',
  'role_id' => 745885,
  'status' => 'PARIKSHAK_FUZZ_dc23c',
  'created_at' => '2026-06-14 07:11:12',
  'updated_at' => '2026-06-14 07:11:12',
  'name' => 'PARIKSHAK_FUZZ_2c087',
  'test1' => 510199,
  'dob' => '2026-06-14 07:11:12',
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
