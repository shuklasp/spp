<?php
// Initialize framework minimally if possible, or just test the class logic
require_once dirname(__DIR__, 3) . '/spp/sppinit.php';

use SPP\Core\ModuleConfigManager;
use SPP\Module;

echo "Running ModuleConfigManagerTest...\n";

// Ensure the class is autoloadable
if (!class_exists('SPP\Core\ModuleConfigManager')) {
    throw new \Exception("ModuleConfigManager class not found via autoloader!");
}

// Test 1: setConfig and getConfig directly
$testVar = 'test_key_' . time();
$testVal = 'test_val_' . time();
$modName = 'testmod';

ModuleConfigManager::setConfig($testVar, $testVal, $modName, 'testapp');

// Retrieve it
$retrieved = ModuleConfigManager::getConfig($testVar, $modName, 'testapp');

if ($retrieved !== $testVal) {
    throw new \Exception("ModuleConfigManagerTest: setConfig or getConfig failed. Expected {$testVal}, got {$retrieved}");
}

// Test 2: Ensure Module class delegates properly
$retrievedViaModule = Module::getConfig($testVar, $modName, 'testapp');

if ($retrievedViaModule !== $testVal) {
    throw new \Exception("ModuleConfigManagerTest: Module::getConfig delegation failed. Expected {$testVal}, got {$retrievedViaModule}");
}

// Test 3: getAppConfig
$appConfig = ModuleConfigManager::getAppConfig($modName, 'testapp');
if (!isset($appConfig[$testVar]) || $appConfig[$testVar] !== $testVal) {
    throw new \Exception("ModuleConfigManagerTest: getAppConfig failed.");
}

echo "ModuleConfigManagerTest Passed.\n";
