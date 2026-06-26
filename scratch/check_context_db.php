<?php
define('SPP_PATH', __DIR__ . '/spp');
require_once 'spp/sppinit.php';

function withContext($targetApp, $callback)
{
    $current = \SPP\Scheduler::getContext();
    if ($current === $targetApp)
        return $callback();
    try {
        try {
            \SPP\Scheduler::getProcObj($targetApp);
        } catch (\Exception $e) {
            new \SPP\App($targetApp, false, 1);
        }
        \SPP\Scheduler::setContext($targetApp);
        $result = $callback();
        \SPP\Scheduler::setContext($current);
        return $result;
    } catch (\Throwable $e) {
        \SPP\Scheduler::setContext($current);
        throw $e;
    }
}

$app = 'lekhak';
withContext($app, function () use ($app) {
    echo "Context: " . \SPP\Scheduler::getContext() . "\n";
    $dbname = \SPP\Module::getConfig('dbname', 'sppdb');
    echo "Configured DB Name: $dbname\n";

    $db = new \SPPMod\SPPDB\SPPDB();
    // We can't easily check the PDO DSN, but we can check if it fails or connects to something.
    echo "Connection attempt finished.\n";
});
