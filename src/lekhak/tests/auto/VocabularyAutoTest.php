<?php
namespace App\Lekhak\Tests\Auto;

use App\Lekhak\Entities\Vocabulary;

/**
 * Auto-generated Test for Vocabulary (Parikshak)
 * Generation Date: 2026-06-14 02:23:45
 */
class VocabularyAutoTest
{
    public static function run()
    {
        echo "Running evaluator for Vocabulary... ";
        try {
            $entity = new Vocabulary();
            $data = array (
  'name' => 'PARIKSHAK_FUZZ_bee57',
  'label' => 'PARIKSHAK_FUZZ_0c15f',
  'description' => 'UNKNOWN_TYPE_text',
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
