<?php
require_once 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
foreach(['spp_users', 'users', 'sppusers'] as $t) {
    if ($db->tableExists($t)) {
        echo "Columns in '$t':\n";
        $res = $db->execute_query("DESCRIBE $t");
        foreach($res as $row) {
            echo "  - " . $row['Field'] . "\n";
        }
    }
}
