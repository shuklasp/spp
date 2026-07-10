<?php
define('SPP_NO_APP', true);
require 'spp/core/class.app.php';
\SPP\App::bootstrap(true);

\SPP\Scheduler::withContext('Samvaad', function() {
    $dir = SPP_APP_DIR . '/serv';
    $routes = \SPPMod\SPPView\RouteScanner::scan($dir);
    echo "ROUTES FROM SCAN:\n";
    print_r($routes);
});
