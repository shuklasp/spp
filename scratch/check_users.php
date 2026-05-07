<?php
require_once 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
try {
    $res = $db->execute_query("SELECT * FROM " . \SPPMod\SPPDB\SPPDB::sppTable('users'));
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
