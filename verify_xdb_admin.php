<?php
require_once 'spp/sppinit.php';
use SPPMod\SPPDB\SPPDB;

try {
    $db = new SPPDB("xdb:dbname=default");
    $res = $db->execute_query("SELECT * FROM users WHERE username='admin'");
    echo "Admin User Found in XDB:\n";
    print_r($res);
    
    $roles = $db->execute_query("SELECT * FROM roles");
    echo "\nRoles in XDB:\n";
    print_r($roles);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
