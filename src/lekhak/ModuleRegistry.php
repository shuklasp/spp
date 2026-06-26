<?php
namespace Lekhak;

class ModuleRegistry
{
    private static $modules = [];
    private static $initialized = false;

    public static function loadModules()
    {
        if (self::$initialized)
            return;
        self::$initialized = true;

        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            // Fetch installed modules
            $installed = $db->execute_query("SELECT machine_name FROM lekhak_modules WHERE status = 1");
            foreach ($installed as $mod) {
                $file = __DIR__ . '/modules/' . $mod['machine_name'] . '/module.php';
                if (file_exists($file)) {
                    $config = require $file;
                    if (isset($config['instance'])) {
                        self::$modules[$mod['machine_name']] = $config['instance'];
                        if (method_exists($config['instance'], 'hook_init')) {
                            $config['instance']->hook_init();
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }
    }

    public static function invokeAll($hook, $args = [])
    {
        self::loadModules();
        $results = [];
        $method = 'hook_' . $hook;
        foreach (self::$modules as $name => $instance) {
            if (method_exists($instance, $method)) {
                $results[$name] = call_user_func_array([$instance, $method], $args);
            }
        }
        return $results;
    }

    public static function invokeAlter($hook, &$data, $context = null)
    {
        self::loadModules();
        $method = 'hook_' . $hook . '_alter';
        foreach (self::$modules as $name => $instance) {
            if (method_exists($instance, $method)) {
                $instance->$method($data, $context);
            }
        }
    }
}

// Global helper function for other parts of the CMS to use
if (!function_exists('lekhak_invoke_all')) {
    function lekhak_invoke_all($hook, $args = [])
    {
        return \Lekhak\ModuleRegistry::invokeAll($hook, $args);
    }
}
if (!function_exists('lekhak_invoke_alter')) {
    function lekhak_invoke_alter($hook, &$data, $context = null)
    {
        \Lekhak\ModuleRegistry::invokeAlter($hook, $data, $context);
    }
}
