<?php
namespace Lekhak\Modules\LekhakDrupalBridge;

class LekhakModuleDrupalBridge
{
    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_lekhak_drupal_bridge_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_lekhak_drupal_bridge_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Load the Drupal global class
        require_once __DIR__ . '/src/Drupal.php';

        // Load the procedural Drupal functions (t, watchdog, etc)
        require_once __DIR__ . '/src/functions.php';

        // Load Form API and Routing Classes
        require_once __DIR__ . '/src/Core/Routing/DrupalRouter.php';
        require_once __DIR__ . '/src/Core/Form/FormState.php';
        require_once __DIR__ . '/src/Core/Form/FormBuilder.php';
        require_once __DIR__ . '/src/Core/Form/ConfigFormBase.php';
        require_once __DIR__ . '/src/Core/Render/Renderer.php';

        // Initialize the basic container
        require_once __DIR__ . '/src/Core/DependencyInjection/Container.php';
        \Drupal::setContainer(new \Lekhak\Modules\LekhakDrupalBridge\Core\DependencyInjection\Container());

        // Scan for and load all .module files for enabled modules
        if (class_exists('\SPPMod\Lekhak\Core\ModuleRegistry')) {
            $allMods = \SPPMod\Lekhak\Core\ModuleRegistry::getModules();
            $installed = [];
            foreach ($allMods as $machineName => $info) {
                if (\SPPMod\Lekhak\Core\ModuleRegistry::isModuleEnabled($machineName)) {
                    $installed[$machineName] = true;
                }
            }
            foreach ($installed as $machineName => $version) {
                if (isset($allMods[$machineName]['path'])) {
                    $moduleFile = $allMods[$machineName]['path'] . '/' . $machineName . '.module';
                    if (file_exists($moduleFile)) {
                        require_once $moduleFile;
                    }
                }
            }

            // Register an autoloader for Drupal namespaces
            spl_autoload_register(function ($class) use ($installed, $allMods) {
                if (strpos($class, 'Drupal\\') === 0) {
                    $parts = explode('\\', $class);
                    $module = $parts[1];
                    if (isset($allMods[$module]['path'])) {
                        // e.g. Drupal\mymodule\Form\SettingsForm -> modules/mymodule/src/Form/SettingsForm.php
                        $relPath = implode('/', array_slice($parts, 2)) . '.php';
                        $path = $allMods[$module]['path'] . '/src/' . $relPath;
                        if (file_exists($path)) {
                            require_once $path;
                        }
                    }
                }
            });
        }

        return true;
    }

    public function hook_request_init()
    {
        \Lekhak\Modules\LekhakDrupalBridge\Core\Routing\DrupalRouter::handleRequest();
    }
}
