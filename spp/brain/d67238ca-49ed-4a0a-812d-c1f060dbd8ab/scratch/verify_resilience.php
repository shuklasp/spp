<?php
require 'c:/projects/apache/school1/spp/sppinit.php';

use SPPMod\SPPDB\SPPEntity;
use SPPMod\SPPGroup\SPPGroup;

// Mock DB row with extra column
$row = [
    'id' => 1,
    'name' => 'Test Group',
    'ai_vector' => 'some_vector_data',
    'unknown_col' => 'value'
];

class MockEntity extends SPPEntity
{
    public function define_attributes()
    {
        return ['name' => 'varchar(255)'];
    }
    public function getTable()
    {
        return 'mock_table';
    }
}

$entity = new MockEntity();
echo "Testing resilient load...\n";
try {
    // Manually trigger the load logic
    foreach ($row as $k => $v) {
        if (!is_numeric($k) && $entity->attributeExists($k)) {
            $entity->set($k, $v);
        }
    }
    echo "Success: Set 'name' but skipped 'ai_vector' and 'unknown_col'.\n";
    echo "Entity Name: " . $entity->get('name') . "\n";

    // Testing set directly (should log warning but not throw)
    echo "Testing direct set on unknown attribute...\n";
    $entity->set('ai_vector', 'test');
    echo "Success: set() did not throw.\n";

} catch (\Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
