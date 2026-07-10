<?php
require 'spp/sppinit.php';
$app = new \SPP\App('Samvaad');

$dir = SPP_APP_DIR . '/src/Samvaad/serv';
try {
    $routes = \SPPMod\SPPView\RouteScanner::scan($dir);
    print_r($routes);
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
