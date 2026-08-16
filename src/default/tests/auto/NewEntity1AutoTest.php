<?php
namespace App\Default\Tests\Auto;

use App\Default\Entities\Newentity1;

/**
 * Auto-generated Test for NewEntity1 (Parikshak)
 * Generation Date: 2026-07-18 08:36:45
 */
class NewEntity1AutoTest
{
    public static function run()
    {
        echo "Running evaluator for NewEntity1... ";
        try {
            $entity = new NewEntity1();
            $data = array (
  'name' => 'PARIKSHAK_FUZZ_6a3a0',
  'created_at' => '2026-07-18 08:36:45',
  'new_column' => 'PARIKSHAK_FUZZ_edc58',
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
