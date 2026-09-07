<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Dummyentity;

/**
 * Auto-generated Test for DummyEntity (Parikshak)
 * Generation Date: 2026-09-04 15:51:23
 */
class DummyEntityAutoTest
{
    public static function run()
    {
        echo "Running evaluator for DummyEntity... ";
        try {
            $entity = new DummyEntity();
            $data = array (
  'name' => 'PARIKSHAK_FUZZ_04786',
  'dob' => '2026-09-04 15:51:23',
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
