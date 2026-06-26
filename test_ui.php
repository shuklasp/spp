<?php
require 'spp/sppinit.php';
try {
    $db = new \SPPMod\SPPDB\SPPDB();
    echo "DB: " . $db->getConnectionSummary() . "<br>\n";
    $stmt = $db->execute_query('SELECT name FROM sqlite_master WHERE type="table"');
    $tables = [];
    foreach ($stmt as $row) {
        $tables[] = $row['name'];
    }
    echo "Tables: " . implode(", ", $tables) . "<br>\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
