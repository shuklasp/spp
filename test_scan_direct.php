<?php
$_SERVER['argv'] = ['spp.php', 'tinker'];
require 'spp.php';
\SPP\Scheduler::withContext('Samvaad', function() {
    $dir = SPP_APP_DIR . '/serv';
    $routes = \SPPMod\SPPView\RouteScanner::scan($dir);
    echo "ROUTES FROM SCAN:\n";
    print_r($routes);
});
