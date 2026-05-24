<?php
require_once __DIR__ . '/sppinit.php';

if (class_exists('\\SPPMod\\Lekhak\\Core\\ModuleRegistry')) {
    \SPPMod\Lekhak\Core\ModuleRegistry::init();
    $modules = \SPPMod\Lekhak\Core\ModuleRegistry::getModules();
    echo "Modules loaded: " . count($modules) . "\n";
    $module = $modules['automated_logout'] ?? null;
    if ($module) {
        echo "Found automated_logout\n";
        echo "Enabled: " . ($module['enabled'] ? 'Yes' : 'No') . "\n";
        print_r($module);
    } else {
        echo "automated_logout not found.\n";
    }
} else {
    echo "ModuleRegistry class not found.\n";
}
