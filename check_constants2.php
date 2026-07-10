<?php
define('SPP_NO_APP', true);
require 'spp/core/class.app.php';
\SPP\App::bootstrap();

\SPP\Scheduler::withContext('Samvaad', function() {
    echo "SPP_BASE_DIR: " . SPP_BASE_DIR . "\n";
    echo "SPP_APP_DIR: " . SPP_APP_DIR . "\n";
    $cacheFile = SPP_BASE_DIR . '/var/cache/routes_Samvaad.php';
    echo "Cache file path: " . $cacheFile . "\n";
    echo "Cache file exists: " . (file_exists($cacheFile) ? "YES" : "NO") . "\n";
    
    // Test the RouteScanner
    $dirsToScan = [
        SPP_APP_DIR . '/controllers',
        SPP_APP_DIR . '/src/Controllers',
        SPP_APP_DIR . '/src/controllers',
        SPP_APP_DIR . '/serv'
    ];
    $routes = [];
    foreach ($dirsToScan as $dir) {
        if (is_dir($dir)) {
            $routes = array_merge($routes, \SPPMod\SPPView\RouteScanner::scan($dir));
        }
    }
    print_r($routes);
});
