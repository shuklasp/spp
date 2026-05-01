<?php
require_once 'spp/sppinit.php';

$modname = 'sppdb';
$appname = 'lekhak';
$configData = [
    'dbname' => 'lekhak' // Changing it back to lekhak
];

echo "Simulating API save call...\n";
\SPP\Module::setAllConfigForApp($configData, $modname, $appname);
echo "Done.\n";
?>
