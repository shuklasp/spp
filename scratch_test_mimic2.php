<?php
define('SPP_BASE_DIR', __DIR__);
require __DIR__ . '/spp/core/class.module.php';
// wait, let's just do:
require __DIR__ . '/spp/core/bootstrap.php'; // wait does this exist?
// Let's use spp.php but skip cli execution by defining something? No.
// Let's just include core files
require __DIR__ . '/spp/core/class.sppdb.php';
require __DIR__ . '/spp/core/class.moduleinstaller.php';
// This is too complex.

\SPP\Scheduler::withContext('sppadmin', function () {
    echo "Testing query with default.spp_modules...\n";
    $db = \SPP\Core\ModuleInstaller::getDb();
    $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_modules');
    $res = $db->execute_query("SELECT * FROM $table WHERE name = ?", ['sppdb']);
    echo "Query result: " . json_encode($res) . "\n";
});
