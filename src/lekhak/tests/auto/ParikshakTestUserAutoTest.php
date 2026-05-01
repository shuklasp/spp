<?php
namespace App\Lekhak\Tests\Auto;

use ParikshakTestUser;

/**
 * Auto-generated Test for ParikshakTestUser (Parikshak)
 * Generation Date: 2026-04-30 08:52:39
 */
class ParikshakTestUserAutoTest
{
    public static function run()
    {
        echo "Running evaluator for ParikshakTestUser... ";
        try {
            $entity = new ParikshakTestUser();
            $data = array (
  'username' => 'PARIKSHAK_FUZZ_cf858',
  'email' => 'PARIKSHAK_FUZZ_aa0d7',
  'age' => 830659,
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
