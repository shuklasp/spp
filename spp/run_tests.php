<?php
/**
 * SPP Unified Automated Test Runner
 *
 * Runs Parikshak / PHPUnit test suites cleanly across any OS (Linux/macOS/Windows)
 * and enforces strict exit codes for CI environments.
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$sppDir = $rootDir . '/spp';

// Set testing environment flags for automated test execution
putenv('APP_ENV=testing');
if (!defined('SPP_ENV')) {
    define('SPP_ENV', 'testing');
}

// Bootstrap framework environment and directory constants
if (file_exists($sppDir . '/sppinit.php')) {
    require_once $sppDir . '/sppinit.php';
}

if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', $sppDir);
}
if (!defined('SPP_APP_DIR')) {
    define('SPP_APP_DIR', $rootDir);
}
if (!defined('SPP_CORE_DIR')) {
    define('SPP_CORE_DIR', $sppDir . '/core');
}
if (!defined('SPP_MODULES_DIR')) {
    define('SPP_MODULES_DIR', $sppDir . '/modules');
}

// Autoload composer dependencies if present
if (file_exists($rootDir . '/vendor/autoload.php')) {
    require_once $rootDir . '/vendor/autoload.php';
}

// Bootstrap SPP Core Autoloader
if (file_exists($sppDir . '/core/class.autoloader.php')) {
    require_once $sppDir . '/core/class.autoloader.php';
    \SPP\Core\Autoloader::register();
}

// Load Parikshak dependencies portably
$parikshakDir = $sppDir . '/modules/optional/parikshak/src';
if (!is_dir($parikshakDir)) {
    $parikshakDir = $sppDir . '/modules/spp/parikshak/src';
}

require_once $parikshakDir . '/AssertionFailedError.php';
require_once $parikshakDir . '/NativeMock.php';
require_once $parikshakDir . '/InteractsWithMockery.php';
require_once $parikshakDir . '/InteractsWithBrowser.php';
require_once $parikshakDir . '/InteractsWithApi.php';
require_once $parikshakDir . '/RefreshDatabase.php';
require_once $parikshakDir . '/SPPTestCase.php';
require_once $parikshakDir . '/TestCase.php';
require_once $parikshakDir . '/SPPTestResponse.php';
require_once $parikshakDir . '/SPPTestRunner.php';

$app = 'default';
$filter = null;
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (str_starts_with($arg, '--filter=')) {
        $filter = substr($arg, 9);
    } elseif (!str_starts_with($arg, '--')) {
        $app = $arg;
    }
}

echo "============================================================\n";
echo " SPP Automated Test Runner\n";
echo " Base Directory: " . SPP_BASE_DIR . "\n";
echo " Target Context: " . $app . ($filter ? " (Filter: {$filter})" : "") . "\n";
echo "============================================================\n\n";

$runner = new \SPPMod\Parikshak\SPPTestRunner();
$results = $runner->run($app, false, $filter);

$total = $results['summary']['total'] ?? 0;
$passed = $results['summary']['passed'] ?? 0;
$failed = $results['summary']['failed'] ?? 0;

if ($failed > 0) {
    echo "\nFailed Test Details:\n";
    echo "------------------------------------------------------------\n";
    foreach ($results['tests'] ?? [] as $t) {
        if ($t['status'] === 'failed') {
            echo "[FAIL] " . ($t['class'] ?? '') . "::" . ($t['method'] ?? '') . "\n";
            echo "       " . ($t['error'] ?? 'Unknown failure') . "\n";
            if (!empty($t['file'])) {
                echo "       File: " . $t['file'] . "\n";
            }
            echo "\n";
        }
    }
}

echo "\n============================================================\n";
echo " Test Run Complete: Total: {$total}, Passed: {$passed}, Failed: {$failed}\n";
echo "============================================================\n";

if ($failed > 0) {
    exit(1);
}

exit(0);
