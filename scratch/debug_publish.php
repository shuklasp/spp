<?php
/**
 * Debug Publish Service
 */
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';

$app = 'lekhak';
new \SPP\App($app, false, 1);
\SPP\Scheduler::setContext($app);

$_POST['action'] = 'publish';
$_POST['title'] = 'Debug Post ' . time();
$_POST['body'] = 'Hello world from real DB';

$response = null;
include 'src/lekhak/serv/publish.php';

echo "Response Variable: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
