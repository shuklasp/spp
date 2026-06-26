<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'spp/sppinit.php';
try {
    \SPP\Scheduler::getProcObj('sppadmin');
} catch (\Exception $e) {
    new \SPP\App('sppadmin');
}
\SPP\Scheduler::setContext('sppadmin');

try {
    $db = new \SPPMod\SPPDB\SPPDB();
    $adapter = (new \ReflectionClass($db))->getProperty('adapter')->getValue($db);
    echo "Adapter class: " . get_class($adapter) . "<br>";
    $dbtype = \SPP\Module::getConfig('dbtype', 'sppdb');
    echo "dbtype (sppdb): " . $dbtype . "<br>";

    // Now trigger RateLimiter
    $rl = new \SPPMod\SppAuth\RateLimiter();
    echo "RateLimiter instantiated successfully.<br>";
    $isLimited = $rl::tooManyAttempts('testuser', '127.0.0.1');
    echo "RateLimiter tooManyAttempts: " . ($isLimited ? 'true' : 'false') . "<br>";
    echo "RateLimiter check passed without error!<br>";
} catch (\Throwable $e) {
    echo "<pre>Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "</pre>";
}
