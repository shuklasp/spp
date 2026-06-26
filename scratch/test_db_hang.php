<?php
define('SPP_BASE_DIR', dirname(__DIR__) . '/spp');
require_once SPP_BASE_DIR . '/sppinit.php';

$appname = 'lekhak';
try {
    try {
        \SPP\Scheduler::getProcObj($appname);
    } catch (\Exception $e) {
        new \SPP\App($appname, false, 1);
    }
    \SPP\Scheduler::setContext($appname);
} catch (\Exception $e) {
    echo "Context failed: " . $e->getMessage() . "\n";
}

$db = new \SPPMod\SPPDB\SPPDB();
echo "Tables in system:\n";
try {
    $users = $db->execute_query("SELECT * FROM users");
    var_dump($users);
} catch (\Exception $e) {
    echo "Select users failed: " . $e->getMessage() . "\n";
}
