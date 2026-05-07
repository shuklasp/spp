<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
try {
    $res = $db->execute_query('DESCRIBE spp_users');
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
