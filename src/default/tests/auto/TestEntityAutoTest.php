<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Testentity;

/**
 * Auto-generated Test for TestEntity (Parikshak)
 * Generation Date: 2026-09-04 15:51:30
 */
class TestEntityAutoTest
{
    public static function run()
    {
        echo "Running evaluator for TestEntity... ";
        try {
            $entity = new TestEntity();
            $data = array (
  'id' => 909369,
  'username' => 'PARIKSHAK_FUZZ_d916f',
  'email' => 'PARIKSHAK_FUZZ_4109a',
  'password_hash' => 'PARIKSHAK_FUZZ_009d1',
  'password' => 'PARIKSHAK_FUZZ_1871a',
  'role_id' => 772436,
  'status' => 'PARIKSHAK_FUZZ_56dc4',
  'mfa_enabled' => 413641,
  'two_factor_enabled' => 185823,
  'two_factor_secret' => 'PARIKSHAK_FUZZ_f2175',
  'password_updated_at' => '2026-09-04 15:51:30',
  'rights_updated_at' => '2026-09-04 15:51:30',
  'created_at' => '2026-09-04 15:51:30',
  'updated_at' => '2026-09-04 15:51:30',
  'name' => 'PARIKSHAK_FUZZ_25a53',
  'test1' => 557165,
  'dob' => '2026-09-04 15:51:30',
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
