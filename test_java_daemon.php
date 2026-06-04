<?php
require 'spp/sppinit.php';

$svc = new \SPP\Services\JavaDaemonService();

echo "Testing Java daemon mode...\n";
$start = microtime(true);
$res = $svc->generate("Test Java 1");
$end = microtime(true);
echo "Result 1: " . print_r($res, true) . "\n";
echo "Time 1: " . round($end - $start, 4) . "s\n\n";

echo "Second call should be instant...\n";
$start = microtime(true);
$res = $svc->generate("Test Java 2");
$end = microtime(true);
echo "Result 2: " . print_r($res, true) . "\n";
echo "Time 2: " . round($end - $start, 4) . "s\n";
