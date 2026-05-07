<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
echo "Listing all tables:\n";
try {
    $res = $db->execute_query('SHOW TABLES');
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
