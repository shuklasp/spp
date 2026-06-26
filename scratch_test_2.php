<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';
\SPP\Module::loadAllModules();
$modules = \SPP\Registry::get('__modobj');
if (is_array($modules)) {
    echo implode(", ", array_keys($modules)) . "\n";
    if (isset($modules['sppdeploy'])) {
        echo "sppdeploy is registered! Class: " . get_class($modules['sppdeploy']) . "\n";
    } else {
        echo "sppdeploy is NOT in __modobj\n";
    }
}
