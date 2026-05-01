<?php
require_once __DIR__ . '/../sppinit.php';
use SPPMod\SPPDB\SPPDB;

$db = new SPPDB();
$res = $db->execute_query("SELECT * FROM lek_nodes");
echo "Total nodes: " . count($res) . "\n";
foreach ($res as $row) {
    echo "ID: {$row['id']} | Title: {$row['title']} | Alias: {$row['alias']}\n";
}
