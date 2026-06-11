<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';

// Simulate LiveAction API request
$_POST['action'] = 'get_codebase_structure';
$_POST['appname'] = 'default';

// Emulate SPP Router or Controller that handles LiveAction
$app = \SPP\App::getInstance();
$la = new \SPP\Core\LiveAction();

// Run the function directly
require_once SPP_APP_DIR . '/spp/admin/services/Docs.php';
live_get_codebase_structure($la, $_POST);

echo $la->getOutput();
