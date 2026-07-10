<?php
require 'spp/modules/spp/sppview/class.routescanner.php';
require 'spp/modules/spp/sppview/Attributes/Route.php';
require 'spp/modules/spp/sppview/Attributes/Middleware.php';
require 'spp/modules/spp/sppview/Attributes/Title.php';

$res = \SPPMod\SPPView\RouteScanner::scan(__DIR__ . '/src/Samvaad/serv');
echo "Scanned Routes:\n";
print_r($res);
