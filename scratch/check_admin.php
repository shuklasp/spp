<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
try {
    $res = $db->execute_query('SELECT username, password FROM users WHERE id=1');
    print_r($res);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
