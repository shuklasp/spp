<?php
require_once __DIR__ . '/../../sppinit.php';
try {
    new \SPP\App('lekhak', false, 1);
    \SPP\Scheduler::setContext('lekhak');
} catch (\Exception $e) {
    echo "App registration failed: " . $e->getMessage() . "\n";
}
try {
    $db = new \SPPMod\SPPDB\SPPDB();
    $tables = $db->execute_query("SHOW TABLES");
    echo "Tables in current context:\n";
    foreach ($tables as $t) {
        echo "- " . array_values($t)[0] . "\n";
    }

    $nodeTable = \SPPMod\SPPDB\SPPDB::sppTable('nodes');
    echo "\nResolved 'nodes' table: $nodeTable\n";

    $check = $db->execute_query("SELECT COUNT(*) as count FROM $nodeTable");
    echo "Count from $nodeTable: " . $check[0]['count'] . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
