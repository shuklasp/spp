<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\TestEntity;

/**
 * Auto-generated Test for TestEntity (Parikshak)
 * Generation Date: 2026-04-26 15:40:23
 */
class TestEntityAutoTest
{
    public static function run()
    {
        echo "Running evaluator for TestEntity... ";
        try {
            $entity = new TestEntity();
            $data = array (
  'id' => 538276,
  'username' => 'PARIKSHAK_FUZZ_6d849',
  'email' => 'PARIKSHAK_FUZZ_040e7',
  'password_hash' => 'PARIKSHAK_FUZZ_23eb4',
  'password' => 'PARIKSHAK_FUZZ_f41c6',
  'role_id' => 788606,
  'status' => 'PARIKSHAK_FUZZ_1ed60',
  'created_at' => '2026-04-26 15:40:23',
  'updated_at' => '2026-04-26 15:40:23',
  'name' => 'PARIKSHAK_FUZZ_ebfae',
  'test1' => 382615,
  'dob' => '2026-04-26 15:40:23',
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
