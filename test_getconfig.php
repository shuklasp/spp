<?php
$authContext = 'default';
require 'spp/sppinit.php';
try { \SPP\Scheduler::getProcObj($authContext); } catch (\Exception $e) { new \SPP\App($authContext); }
\SPP\Scheduler::setContext($authContext);

$appname = \SPP\Scheduler::getContext();
$modname = 'sppdb';
$varname = 'dbtype';

$modsConfDir = \SPP\Module::getEffectiveModsConfDir($modname, $appname);
$isolatedConf = $modsConfDir . DIRECTORY_SEPARATOR . $modname . DIRECTORY_SEPARATOR . 'config.yml';

echo "Context: $appname\n";
echo "Isolated Conf: $isolatedConf\n";
if (file_exists($isolatedConf)) {
    echo "Exists!\n";
    $yamlData = \Symfony\Component\Yaml\Yaml::parseFile($isolatedConf);
    $val = $yamlData['variables'][$varname] ?? ($yamlData[$varname] ?? null);
    echo "Value found in YAML: " . var_export($val, true) . "\n";
} else {
    echo "Does not exist.\n";
}

$dbtype = \SPP\Module::getConfig('dbtype', 'sppdb');
echo "\nModule::getConfig returned: " . var_export($dbtype, true) . "\n";
