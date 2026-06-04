<?php
require 'spp/sppinit.php';

$svc = new \SPP\Services\PythonDaemonService();

echo "Testing daemon mode (first call will take ~2 seconds to load model)...\n";
$start = microtime(true);
$res = $svc->generate("Test 1");
$end = microtime(true);
echo "Result 1: {$res}\n";
echo "Time 1: " . round($end - $start, 4) . "s\n\n";

echo "Second call should be instant...\n";
$start = microtime(true);
$res = $svc->generate("Test 2");
$end = microtime(true);
echo "Result 2: {$res}\n";
echo "Time 2: " . round($end - $start, 4) . "s\n";
