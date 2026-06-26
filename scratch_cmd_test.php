<?php
require_once 'c:/projects/apache/school1/spp/sppinit.php';
\SPP\Module::loadAllModules();
$modObj = \SPP\Module::getModule('sppdeploy');
if ($modObj) {
    echo "Module found.\n";
    $modDir = rtrim($modObj->ModPath, '/\\');
    echo "ModDir: $modDir\n";
    $modName = $modObj->InternalName ?? basename($modDir);
    echo "ModName: $modName\n";
    $nsMod = str_replace('.', '\\', ucwords($modName, '.'));
    $expectedNamespace = "SPPMod\\{$nsMod}\\Commands";
    echo "Expected Namespace: $expectedNamespace\n";

    foreach (glob($modDir . '/commands/*.php') as $file) {
        $className = basename($file, '.php');
        $class = "{$expectedNamespace}\\{$className}";
        echo "Trying: $class in $file\n";
        require_once $file;
        if (class_exists($class)) {
            echo "  [OK] Class exists\n";
        } else {
            echo "  [FAIL] Class DOES NOT exist. What is loaded?\n";
            $content = file_get_contents($file);
            if (preg_match('/namespace\s+([^;]+);/i', $content, $m)) {
                echo "  File Namespace: " . $m[1] . "\n";
            }
        }
    }
} else {
    echo "Module not loaded.\n";
}
