<?php
require_once __DIR__ . '/spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
echo "SQLite DB File: " . $db->getDbPath() . "\n";
echo "Tables list:\n";
$tables = $db->execute_query("SELECT name FROM sqlite_master WHERE type='table'");
print_r($tables);
foreach ($tables as $t) {
    if (strpos($t['name'], 'term') !== false || strpos($t['name'], 'vocab') !== false) {
        echo "Schema for " . $t['name'] . ":\n";
        print_r($db->execute_query("PRAGMA table_info(" . $t['name'] . ")"));
    }
}
