<?php
$_SERVER['REQUEST_URI'] = '/admin/config/drupal_test/settings';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
require 'spp/sppinit.php';

// Force SPP App base URL to be empty for this test
class MockApp {
    public static function getBaseUrl() { return ''; }
}

require_once __DIR__ . '/src/lekhak/modules/lekhak_drupal_bridge/module.php';
$bridge = new \Lekhak\Modules\LekhakDrupalBridge\LekhakModuleDrupalBridge();
$bridge->hook_init(); // Register autoloader & load .module files

require_once __DIR__ . '/src/lekhak/modules/lekhak_drupal_bridge/src/Core/Routing/DrupalRouter.php';
require_once __DIR__ . '/src/lekhak/modules/lekhak_drupal_bridge/src/Core/Form/FormState.php';
require_once __DIR__ . '/src/lekhak/modules/lekhak_drupal_bridge/src/Core/Form/FormBuilder.php';
require_once __DIR__ . '/src/lekhak/modules/lekhak_drupal_bridge/src/Core/Form/ConfigFormBase.php';
require_once __DIR__ . '/src/lekhak/modules/lekhak_drupal_bridge/src/Core/Render/Renderer.php';

echo "Before handleRequest. URI is: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Is module enabled? " . (\SPPMod\Lekhak\Core\ModuleRegistry::isModuleEnabled('drupal_test_module') ? 'Yes' : 'No') . "\n";
$res = \Lekhak\Modules\LekhakDrupalBridge\Core\Routing\DrupalRouter::handleRequest();
echo "After handleRequest (returned " . var_export($res, true) . ")\n";
