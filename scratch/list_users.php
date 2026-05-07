<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
echo "Listing users in spp_users:\n";
try {
    $res = $db->execute_query('SELECT * FROM spp_users');
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
