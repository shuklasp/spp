<?php
require_once 'spp/sppinit.php';

// Mock request
$_REQUEST['action'] = 'TestDiscovery';
$action = $_REQUEST['action'];

echo "Testing SPPAjax::resolveAndExecute for action: $action\n";

// We need to capture the JSON output since SPPAjax::resolveAndExecute calls exit
ob_start();
try {
    \SPPMod\SPPAjax\SPPAjax::resolveAndExecute($action, $_REQUEST);
} catch (\Exception $e) {
    echo "Caught: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

echo "Output captured:\n";
echo $output . "\n";
