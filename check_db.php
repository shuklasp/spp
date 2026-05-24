<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
try {
    print_r($db->execute_query('SELECT * FROM lekhak_modules'));
} catch (Exception $e) {
    echo "No table lekhak_modules";
}
