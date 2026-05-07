<?php
require_once 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
$tables = $db->execute_query("SHOW TABLES");
echo "Current Context: " . \SPP\Scheduler::getContext() . "\n";
echo "Resolved Users Table: " . \SPPMod\SPPDB\SPPDB::sppTable('users') . "\n";

$tablesToCheck = ['spp_users', 'users', 'sppusers'];
foreach ($tablesToCheck as $table) {
    if ($db->tableExists($table)) {
        $res = $db->execute_query("SELECT id, username FROM $table WHERE username='admin'");
        echo "Table '$table': " . (count($res) > 0 ? "FOUND (ID: " . $res[0]['id'] . ")" : "NOT FOUND") . "\n";
    } else {
        echo "Table '$table' does not exist.\n";
    }
}
