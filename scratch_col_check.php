<?php
require_once 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$res = $db->execute_query("DESCRIBE users");
echo "Columns in 'users' table:\n";
foreach($res as $row) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}
