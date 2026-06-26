<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';
\SPP\Module::loadAllModules();
$mod = \SPP\Module::getModule('sppdeploy');
if ($mod) {
    echo 'Path: ' . ($mod->ModPath ?? 'null') . "\n";
    echo 'Class: ' . get_class($mod) . "\n";
    echo 'Is \SPP\Module? ' . ($mod instanceof \SPP\Module ? 'Yes' : 'No') . "\n";
} else {
    echo "Module not found\n";
}
