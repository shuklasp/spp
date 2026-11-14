<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Testentity;

/**
 * Auto-generated Test for TestEntity (Parikshak)
 * Generation Date: 2026-11-01 12:52:20
 */
class TestEntityAutoTest
{
    public static function run()
    {
        echo "Running evaluator for TestEntity... ";
        try {
            $entity = new TestEntity();
            $data = array (
  'id' => 19609,
  'username' => 'PARIKSHAK_FUZZ_c1999',
  'email' => 'PARIKSHAK_FUZZ_0beeb',
  'password_hash' => 'PARIKSHAK_FUZZ_4ce88',
  'password' => 'PARIKSHAK_FUZZ_82584',
  'role_id' => 135254,
  'status' => 'PARIKSHAK_FUZZ_56c18',
  'mfa_enabled' => 557168,
  'two_factor_enabled' => 396887,
  'two_factor_secret' => 'PARIKSHAK_FUZZ_94ef6',
  'password_updated_at' => '2026-11-01 12:52:20',
  'rights_updated_at' => '2026-11-01 12:52:20',
  'created_at' => '2026-11-01 12:52:20',
  'updated_at' => '2026-11-01 12:52:20',
  'name' => 'PARIKSHAK_FUZZ_8aa13',
  'test1' => 245373,
  'dob' => '2026-11-01 12:52:20',
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
