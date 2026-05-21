<?php
require_once 'spp/sppinit.php';
\SPP\Module::loadAllModules();
$modules = \SPP\Registry::get('__modobj');
if (!isset($modules['parikshak'])) {
    echo "parikshak not found in __modobj!" . PHP_EOL;
    exit(1);
}
$modObj = $modules['parikshak'];
$modDir = $modObj->ModPath;
echo "modDir: " . $modDir . PHP_EOL;
$files = glob($modDir . '/commands/*.php');
print_r($files);

foreach ($files as $file) {
    require_once $file;
    $className = basename($file, '.php');
    $modName = $modObj->InternalName ?? basename($modDir);
    $nsMod = str_replace('.', '\\', ucwords($modName, '.'));
    $class = "SPPMod\\{$nsMod}\\Commands\\{$className}";
    echo "Checking class: " . $class . PHP_EOL;
    var_dump(class_exists($class));
}
