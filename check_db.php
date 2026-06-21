<?php
require 'spp/sppinit.php';
$db = new \SPPMod\SPPDB\SPPDB();
echo "Default Context: " . $db->getConnectionSummary() . "\n";
\SPP\Scheduler::setContext('sppadmin');
$db2 = new \SPPMod\SPPDB\SPPDB();
echo "SPPAdmin Context: " . $db2->getConnectionSummary() . "\n";
