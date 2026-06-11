<?php
namespace SPP;

class SPPObject {}
class Scheduler {
    public static function getContext() { return 'default'; }
}

require_once 'spp/core/class.registry.php';

use SPP\Registry;

echo "Testing Registry...\n";

Registry::register('config=>db=>host', 'localhost');
Registry::register('config=>db=>user', 'root');
Registry::register('config=>db=>pass', 'password');

$host = Registry::get('config=>db=>host');
echo "Host: " . ($host === 'localhost' ? "OK" : "FAIL") . "\n";

$db = Registry::get('config=>db');
echo "DB array: " . (is_array($db) && $db['user'] === 'root' ? "OK" : "FAIL") . "\n";

Registry::remove('config=>db=>pass');
$pass = Registry::get('config=>db=>pass');
echo "Pass removed: " . ($pass === false ? "OK" : "FAIL") . "\n";

Registry::register('__shared=>test', 'hello');
$shared = Registry::get('__shared=>test');
echo "Shared: " . ($shared === 'hello' ? "OK" : "FAIL") . "\n";

if (!defined('SPP_BASE_DIR')) {
    define('SPP_BASE_DIR', __DIR__);
}

Registry::forceSyncShared();

if (file_exists(__DIR__ . '/var/shared/registry.json')) {
    echo "Shared JSON exists: OK\n";
    $json = file_get_contents(__DIR__ . '/var/shared/registry.json');
    if (strpos($json, 'hello') !== false) {
        echo "Shared JSON contains test: OK\n";
    } else {
        echo "Shared JSON contains test: FAIL\n";
    }
} else {
    echo "Shared JSON exists: FAIL\n";
}

echo "Done.\n";
