<?php

// SPP Cohesion Test Script
// Verifies that the Core system can boot independently of modules, and tests dependency injection.

define('SPP_BASE_DIR', __DIR__ . '/spp');
define('SPP_APP_DIR', __DIR__);
define('SPP_DS', DIRECTORY_SEPARATOR);

// Mock a lightweight environment
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
$_SERVER['HTTP_HOST'] = 'localhost';

try {
    require_once __DIR__ . '/spp/sppinit.php';
    
    echo "[PASS] Core loaded without fatal errors.\n";
    
    // Test DB Provider mapping
    if (class_exists('\\SPP\\DB')) {
        echo "[PASS] SPP\\DB facade exists.\n";
        try {
            \SPP\DB::getInstance();
            echo "[FAIL] SPP\\DB should throw an exception if provider is not loaded.\n";
        } catch (\Exception $e) {
            echo "[PASS] SPP\\DB throws exception when no provider is set: " . $e->getMessage() . "\n";
        }
    } else {
        echo "[FAIL] SPP\\DB facade missing.\n";
    }

    // Now let's try to load the sppdb module
    if (file_exists(__DIR__ . '/spp/modules/spp/sppdb/modinit.php')) {
        require_once __DIR__ . '/spp/modules/spp/sppdb/modinit.php';
        try {
            $db = \SPP\DB::getInstance();
            echo "[PASS] SPP\\DB successfully resolved the SPPDB provider from the module.\n";
            echo "[PASS] Provider class: " . get_class($db) . "\n";
        } catch (\Exception $e) {
            echo "[FAIL] SPP\\DB could not resolve provider after modinit.\n";
        }
    } else {
        echo "[WARN] sppdb module not found for testing.\n";
    }
    
    // Test Event system
    echo "[INFO] Testing logging event...\n";
    $logFired = false;
    \SPP\SPPEvent::listen('log', function($params) use (&$logFired) {
        $logFired = true;
        echo "[PASS] Logging event caught: [" . $params->get('level') . "] " . $params->get('message') . "\n";
    });
    
    \SPP\SPPEvent::fireEvent('log', new \SPP\EventParams(['level' => 'info', 'message' => 'Cohesion test log']));
    if (!$logFired) echo "[FAIL] Logging event was not caught.\n";

    echo "\n=== ALL COHESION TESTS COMPLETED ===\n";

} catch (\Throwable $e) {
    echo "[FATAL] System crashed during cohesion test: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
