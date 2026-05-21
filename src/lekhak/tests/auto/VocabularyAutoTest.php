<?php
namespace App\Lekhak\Tests\Auto;

use App\Lekhak\Entities\Vocabulary;

/**
 * Auto-generated Test for Vocabulary (Parikshak)
 * Generation Date: 2026-05-19 02:59:22
 */
class VocabularyAutoTest
{
    public static function run()
    {
        echo "Running evaluator for Vocabulary... ";
        try {
            $entity = new Vocabulary();
            $data = array (
  'name' => 'PARIKSHAK_FUZZ_b148e',
  'label' => 'PARIKSHAK_FUZZ_29c4a',
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
