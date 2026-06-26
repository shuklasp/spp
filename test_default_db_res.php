<?php
$authContext = 'default';
require 'spp/sppinit.php';
try {
    \SPP\Scheduler::getProcObj($authContext);
} catch (\Exception $e) {
    new \SPP\App($authContext);
}
\SPP\Scheduler::setContext($authContext);

$db = new \SPPMod\SPPDB\SPPDB();
try {
    $res = $db->execute_query('SELECT * FROM login_attempts');
    echo "Query succeeded. Res: " . var_export($res, true) . "<br>";
} catch (\Exception $e) {
    echo "Query failed: " . $e->getMessage() . "<br>";
}
