<?php
require_once 'spp/spp.php';
$db = new \SPPMod\SPPDB\SPPDB();
try {
    $db->execute_query("CREATE TABLE IF NOT EXISTS test_autoinc (placeholder INT)");
    $db->execute_query("ALTER TABLE test_autoinc ADD uid INTEGER PRIMARY KEY AUTO_INCREMENT");
    echo "Success!\n";
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
