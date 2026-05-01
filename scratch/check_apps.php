<?php
require_once __DIR__ . '/../spp/sppinit.php';
$apps = \SPP\App::getGlobalSettings('apps');
echo "Registered Apps:\n";
print_r(array_keys($apps));
echo "\nLekhak Metadata:\n";
print_r($apps['lekhak'] ?? 'NOT FOUND');
