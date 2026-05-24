<?php
require 'spp/sppinit.php';

echo "Testing invokeAll('request_init')\n";

$_SERVER['REQUEST_URI'] = '/school1/lekhak/admin/sankhyaki';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

\Lekhak\ModuleRegistry::invokeAll('request_init');

echo "Finished invokeAll\n";
