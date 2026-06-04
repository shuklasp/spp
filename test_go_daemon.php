<?php
require 'spp/sppinit.php';

$svc = new \SPP\Services\GoDaemonService();

echo "Testing Go daemon mode...\n";
$start = microtime(true);
$res = $svc->generate("Test Go 1");
$end = microtime(true);
echo "Result 1: {$res}\n";
echo "Time 1: " . round($end - $start, 4) . "s\n\n";

echo "Second call should be instant...\n";
$start = microtime(true);
$res = $svc->generate("Test Go 2");
$end = microtime(true);
echo "Result 2: {$res}\n";
echo "Time 2: " . round($end - $start, 4) . "s\n";
