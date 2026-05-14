<?php
require_once __DIR__ . '/../spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$res = $db->execute_query("SHOW TABLES");
foreach ($res as $row) {
    echo array_values($row)[0] . "\n";
}
