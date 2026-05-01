<?php
require_once 'vendor/autoload.php';
// Define request URI for context detection before init
$_SERVER['REQUEST_URI'] = '/lekhak/';
require_once 'spp/sppinit.php';

echo "Active Context: " . \SPP\Scheduler::getContext() . "\n";

echo "Discovery Test:\n";
$entities = \SPPMod\SPPDB\SPPDB::getRouteEntities();
print_r($entities);

echo "\nRouting Test for alias '1.0/api/SPP/Core/Container':\n";
$page = \SPPMod\SPPView\Pages::getPage('1.0/api/SPP/Core/Container');
print_r($page);
