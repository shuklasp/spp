<?php
$_SERVER['argv'] = ['spp.php', 'tinker'];
require 'spp.php';
\SPP\Scheduler::withContext('Samvaad', function() {
    echo "SPP_BASE_DIR: " . SPP_BASE_DIR . "\n";
    echo "SPP_APP_DIR: " . SPP_APP_DIR . "\n";
    $cacheFile = SPP_BASE_DIR . '/var/cache/routes_Samvaad.php';
    echo "Cache file path: " . $cacheFile . "\n";
    echo "Cache file exists: " . (file_exists($cacheFile) ? "YES" : "NO") . "\n";
});
