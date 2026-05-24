<?php
namespace App\Lekhak\Serv;

use SPPMod\Lekhak\Core\ModuleRegistry;
use SPP\SPPConfig;
use SPPMod\SPPView\ViewFormBuilder;

class ConfigController extends AdminController
{
    public function manage($moduleMachineName)
    {
        // Get the module instance from registry
        $modules = ModuleRegistry::getModules();
        if (!isset($modules[$moduleMachineName]) || empty($modules[$moduleMachineName]['status'])) {
            header("HTTP/1.0 404 Not Found");
            echo "Module not found or not enabled.";
            exit;
        }

        $moduleInfo = $modules[$moduleMachineName];
        $camelName = str_replace(' ', '', ucwords(str_replace('_', ' ', $moduleMachineName)));
        $className = "\\Lekhak\\Modules\\LekhakModule{$camelName}\\LekhakModule{$camelName}";

        if (!class_exists($className)) {
            $file = $moduleInfo['path'] . '/module.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }

        $schema = [];
        if (class_exists($className) && method_exists($className, 'hook_config_form')) {
            $schema = call_user_func([$className, 'hook_config_form']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($schema as $key => $definition) {
                if (isset($_POST[$key])) {
                    $val = $_POST[$key];
                    if ($definition['type'] === 'checkbox') {
                        $val = true;
                    }
                    SPPConfig::set("module:{$moduleMachineName}:{$key}", $val);
                } else {
                    if ($definition['type'] === 'checkbox') {
                        SPPConfig::set("module:{$moduleMachineName}:{$key}", false);
                    }
                }
            }
            $_SESSION['flash_success'] = "Configuration saved successfully.";
            header("Location: " . $this->getAppRoot() . "/admin/config/" . $moduleMachineName);
            exit;
        }

        // Hydrate schema with current values
        foreach ($schema as $key => &$definition) {
            $current = SPPConfig::get("module:{$moduleMachineName}:{$key}");
            if ($current !== null) {
                $definition['value'] = $current;
            } else {
                $definition['value'] = $definition['default'] ?? null;
            }
        }

        return $this->render("config", [
            'title' => ($moduleInfo['name'] ?? $moduleMachineName) . ' Settings',
            'subtitle' => 'Configure module-specific settings.',
            'moduleName' => $moduleMachineName,
            'schema' => $schema
        ]);
    }
}
