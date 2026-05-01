<?php
define('SPP_BASE_DIR', __DIR__ . '/../spp');
require_once __DIR__ . '/../spp/sppinit.php';

$appname = 'lekhak';
echo "Context: $appname\n";

new \SPP\App($appname, false, 1);
\SPP\Scheduler::setContext($appname);

$deltas = \SPP\Module::getSystemUpdateDeltas();

echo "Resolved table names:\n";
echo "User: " . \SPPMod\SPPDB\SPPDB::sppTable('users') . "\n";
echo "Role: " . \SPPMod\SPPDB\SPPDB::sppTable('roles') . "\n";
echo "Right: " . \SPPMod\SPPDB\SPPDB::sppTable('rights') . "\n";

echo "Deltas found:\n";
print_r($deltas);
