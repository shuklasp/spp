<?php
require 'spp/sppinit.php';
\SPP\Scheduler::setContext('default');
$db = new \SPPMod\SPPDB\SPPDB();
echo $db->sppTable('audit_logs') . "\n";
