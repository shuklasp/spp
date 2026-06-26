<?php
// Just add this right after \SPP\Scheduler::setContext($authContext); in api.php
// Wait, I will edit api.php to dump the DB path and exit!
// Actually, I can just write a test_api.php that does exactly what api.php does.
$authContext = 'sppadmin';
require 'spp/sppinit.php';
try {
    \SPP\Scheduler::getProcObj($authContext);
} catch (\Exception $e) {
    new \SPP\App($authContext);
}
\SPP\Scheduler::setContext($authContext);

$db = new \SPPMod\SPPDB\SPPDB();
echo "sppadmin context DB: " . $db->getConnectionSummary() . "<br>\n";

$db2 = new \SPPMod\SPPDB\SPPDB('default');
echo "default context DB: " . $db2->getConnectionSummary() . "<br>\n";
