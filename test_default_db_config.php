<?php
$authContext = 'default';
require 'spp/sppinit.php';
try {
    \SPP\Scheduler::getProcObj($authContext);
} catch (\Exception $e) {
    new \SPP\App($authContext);
}
\SPP\Scheduler::setContext($authContext);

$dbtype = \SPP\Module::getConfig('dbtype', 'sppdb');
$sqlite_path = \SPP\Module::getConfig('sqlite_path', 'sppdb');

echo "dbtype: " . var_export($dbtype, true) . "<br>";
echo "sqlite_path: " . var_export($sqlite_path, true) . "<br>";

$db = new \SPPMod\SPPDB\SPPDB();
$adapter = (new \ReflectionClass($db))->getProperty('adapter')->getValue($db);
echo "adapter class: " . get_class($adapter) . "<br>";
