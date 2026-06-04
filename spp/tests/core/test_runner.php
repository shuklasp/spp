<?php
echo "SPP V5 Core Test Runner\n";
echo "=======================\n";

define('SPP_DIR', dirname(__DIR__, 2));
define('SPP_APP_DIR', dirname(SPP_DIR));

// Let autoloader do the work
spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'SPP\\')) {
        $path = SPP_DIR . '/core/class.' . strtolower(substr($class, 4)) . '.php';
        if (file_exists($path)) require_once $path;
    } elseif (str_starts_with($class, 'SPPMod\\SPPEntity\\')) {
        $path = SPP_DIR . '/modules/spp/sppentity/class.' . strtolower(substr($class, 17)) . '.php';
        if (file_exists($path)) require_once $path;
    } elseif (str_starts_with($class, 'SPPMod\\SPPDB\\')) {
        $path = SPP_DIR . '/modules/spp/sppdb/class.' . strtolower(substr($class, 14)) . '.php';
        if (file_exists($path)) require_once $path;
    }
});

$tests = [
    __DIR__ . '/RegistryTest.php',
    __DIR__ . '/EventManagerTest.php',
    __DIR__ . '/SPPEntityQueryTest.php'
];

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    try {
        require_once $test;
        $passed++;
    } catch (\Exception $e) {
        echo "[FAILED] " . basename($test) . ": " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "=======================\n";
echo "Tests Passed: $passed\n";
echo "Tests Failed: $failed\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
