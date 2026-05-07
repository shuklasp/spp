<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
echo "Listing users in 'users' table:\n";
try {
    $res = $db->execute_query('SELECT id, username FROM users');
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
