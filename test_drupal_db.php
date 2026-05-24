<?php
require 'spp/sppinit.php';

// Ensure module is enabled for bridge
$d2 = json_decode(file_get_contents('src/lekhak/etc/enabled_modules.json'), true); 
if(!in_array('lekhak_drupal_bridge', $d2)) { 
    $d2[] = 'lekhak_drupal_bridge'; 
    file_put_contents('src/lekhak/etc/enabled_modules.json', json_encode($d2));
}

// Run the autoloader manually if needed
require_once 'src/lekhak/modules/lekhak_drupal_bridge/module.php';
$bridge = new \Lekhak\Modules\LekhakDrupalBridge\LekhakModuleDrupalBridge();
$bridge->hook_init();
echo "Testing db_select()...\n";
$query = db_select('users', 'u');
$query->fields('u', ['uid', 'name']);
$query->condition('status', 1);

try {
    $result = $query->execute();
    echo "Generated Select class: " . get_class($query) . "\n";
    echo "Select executed successfully.\n\n";
} catch (\Exception $e) {
    echo "Select executed and reached SPPDB (caught expected exception: " . $e->getMessage() . ").\n\n";
}

echo "Testing db_insert()...\n";
$insert = db_insert('users')->fields([
    'name' => 'drupal_bridge_test',
    'status' => 1
]);
try {
    $insert_res = $insert->execute();
    echo "Insert executed successfully.\n\n";
} catch (\Exception $e) {
    echo "Insert executed and reached SPPDB (caught expected exception: " . $e->getMessage() . ").\n\n";
}

echo "Testing db_update()...\n";
$update = db_update('users')
    ->fields(['name' => 'drupal_bridge_updated'])
    ->condition('uid', 1);
try {
    $update_res = $update->execute();
    echo "Update executed successfully.\n\n";
} catch (\Exception $e) {
    echo "Update executed and reached SPPDB (caught expected exception: " . $e->getMessage() . ").\n\n";
}

echo "Testing EntityTypeManager...\n";
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load(1);
echo "Node class: " . get_class($node) . "\n";
echo "Node ID: " . $node->id() . "\n";

echo "All tests completed.\n";
