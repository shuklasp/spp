<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$query = "CREATE TABLE lek_config (propname VARCHAR(100), propval VARCHAR(500) NOT NULL, tabname VARCHAR(100), colname VARCHAR(100), pkname VARCHAR(100), pkval VARCHAR(100), PRIMARY KEY (propname))";
try {
    $db->execute_query($query);
    echo "Success!\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
