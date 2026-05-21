<?php
namespace App\Lekhak\Tests\Auto;

use App\Lekhak\Entities\Contenttype;

/**
 * Auto-generated Test for ContentType (Parikshak)
 * Generation Date: 2026-05-19 03:03:43
 */
class ContentTypeAutoTest
{
    public static function run()
    {
        echo "Running evaluator for ContentType... ";
        try {
            $entity = new ContentType();
            $data = array (
  'name' => 'PARIKSHAK_FUZZ_75cf4',
  'label' => 'PARIKSHAK_FUZZ_f532c',
  'description' => 'UNKNOWN_TYPE_text',
  'storage_strategy' => 'PARIKSHAK_FUZZ_9d78a',
  'help_text' => 'UNKNOWN_TYPE_text',
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
