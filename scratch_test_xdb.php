<?php
require_once __DIR__ . '/spp/sppinit.php';
\SPP\Scheduler::withContext('sppadmin', function() {
    $db = new \SPPMod\SppDb\SPPDB();
    $table = \SPPMod\SppDb\SPPDB::sppTable('spp_modules');
    echo "Table: $table\n";
    $res = $db->execute_query("SELECT * FROM $table WHERE name = ?", ['sppdb']);
    print_r($res);
});
