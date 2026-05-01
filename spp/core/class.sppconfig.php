<?php
namespace SPP;

use Symfony\Component\Yaml\Yaml;

/**
 * class SPP\SPPConfig
 * 
 * Core authority for reading and writing setting variables across the framework.
 * Standardizes global settings into global-settings.yml and per-app settings into settings.yml.
 */
class SPPConfig extends \SPP\SPPObject
{
    private static $cache = [];

    private static $schemas = [];

    /**
     * Registers a validation schema for a configuration namespace.
     * 
     * @param string $namespace 'app', 'global', or 'mod:<modname>'
     * @param array $schema
     */
    public static function registerSchema(string $namespace, array $schema): void
    {
        self::$schemas[$namespace] = $schema;
    }

    /**
     * Validates a value against a registered schema.
     * 
     * @param string $key
     * @param mixed $value
     * @param string $namespace
     * @throws \SPP\SPPException
     */
    public static function validate(string $key, mixed $value, string $namespace = 'app'): void
    {
        $schema = self::$schemas[$namespace] ?? null;
        if (!$schema || !isset($schema[$key])) return;

        $def = $schema[$key];
        $type = $def['type'] ?? 'string';
        
        switch ($type) {
            case 'boolean':
            case 'bool':
                if (!is_bool($value) && !in_array($value, ['true', 'false', '1', '0', 1, 0], true)) {
                    throw new \SPP\SPPException("Validation failed for '{$key}' in '{$namespace}': Expected boolean.");
                }
                break;
            case 'integer':
            case 'int':
            case 'number':
                if (!is_numeric($value)) {
                    throw new \SPP\SPPException("Validation failed for '{$key}' in '{$namespace}': Expected numeric value.");
                }
                break;
            case 'array':
                if (!is_array($value)) {
                    throw new \SPP\SPPException("Validation failed for '{$key}' in '{$namespace}': Expected array.");
                }
                break;
            case 'string':
            case 'text':
            case 'password':
            case 'select':
                if (!is_scalar($value) && !is_null($value)) {
                    throw new \SPP\SPPException("Validation failed for '{$key}' in '{$namespace}': Expected string/scalar value.");
                }
                break;
        }
    }

    /**
     * Retrieves a setting value.
     * 
     * Key formats:
     * - "app:site_name" -> <AppEtcDir>/settings.yml
     * - "global:debug" -> spp/etc/global-settings.yml (under 'settings' block)
     * - "sys:apps.lekhak.base_url" -> spp/etc/global-settings.yml (infrastructure)
     * - "env:DB_PASS" -> $_ENV or getenv()
     * - "mod:sppdb:table_prefix" -> SPP\Module::getConfig()
     * - "key" (no prefix) -> Checks "app:", then "global:", then "sys:", then "env:"
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $appname = \SPP\Scheduler::getContext() ?: 'default';
        
        // 1. Check in-memory cache
        $cacheKey = "{$appname}::{$key}";
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // 2. Check compiled file cache (production mode)
        if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
            $compiled = self::loadCompiled($appname);
            if (isset($compiled[$key])) {
                return $compiled[$key];
            }
        }

        $parts = explode(':', $key);
        $type = count($parts) > 1 ? $parts[0] : null;

        $val = null;
        switch ($type) {
            case 'app':
                $val = self::getFromApp(implode(':', array_slice($parts, 1)), $appname);
                break;
            case 'global':
                $val = self::getFromGlobal(implode(':', array_slice($parts, 1)));
                break;
            case 'sys':
                $val = self::getFromSys(implode(':', array_slice($parts, 1)));
                break;
            case 'env':
                $val = self::getFromEnv(implode(':', array_slice($parts, 1)));
                break;
            case 'mod':
                if (count($parts) >= 3) {
                    $modname = $parts[1];
                    $modkey = implode(':', array_slice($parts, 2));
                    $val = \SPP\Module::getConfig($modkey, $modname, $appname);
                }
                break;
            case 'db':
                $val = self::getFromDb(implode(':', array_slice($parts, 1)), $appname);
                break;
            default:
                // No prefix priority: app > global > sys > env
                $val = self::getFromApp($key, $appname);
                if ($val === null) $val = self::getFromGlobal($key);
                if ($val === null) $val = self::getFromSys($key);
                if ($val === null) $val = self::getFromEnv($key);
                break;
        }

        $result = ($val !== null) ? $val : $default;
        self::$cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Persists a setting value.
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, mixed $value): void
    {
        $appname = \SPP\Scheduler::getContext() ?: 'default';
        $parts = explode(':', $key);
        $type = count($parts) > 1 ? $parts[0] : 'app';
        $realKey = count($parts) > 1 ? implode(':', array_slice($parts, 1)) : $key;

        // Perform semantic validation if a schema is registered
        if ($type === 'mod' && count($parts) >= 3) {
            self::validate($realKey, $value, "mod:{$parts[1]}");
        } else {
            self::validate($realKey, $value, $type);
        }

        switch ($type) {
            case 'app':
                self::putToApp($realKey, $value, $appname);
                break;
            case 'global':
                self::putToGlobal($realKey, $value);
                break;
            case 'sys':
                self::putToSys($realKey, $value);
                break;
            case 'mod':
                if (count($parts) >= 3) {
                    $modname = $parts[1];
                    $modkey = implode(':', array_slice($parts, 2));
                    \SPP\Module::setConfig($modkey, $value, $modname, $appname);
                }
                break;
            case 'db':
                self::putToDb($realKey, $value, $appname);
                break;
        }

        // Invalidate in-memory and file cache
        unset(self::$cache["{$appname}::{$key}"]);
        if ($type === 'app' || $type === 'global' || $type === 'sys') {
            unset(self::$cache["{$appname}::{$realKey}"]);
            self::clearCompiled($appname);
        }
    }

    private static function getFromGlobal(string $key): mixed
    {
        $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
        $data = self::loadYaml($file);
        return self::getNestedValue($data['settings'] ?? [], $key);
    }

    private static function getFromSys(string $key): mixed
    {
        $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
        $data = self::loadYaml($file);
        return self::getNestedValue($data, $key);
    }

    private static function getFromEnv(string $key): mixed
    {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        $val = getenv($key);
        return $val === false ? null : $val;
    }

    private static function getFromApp(string $key, string $appname): mixed
    {
        $app = \SPP\App::getApp($appname);
        $file = $app->getAppConfDir() . SPP_DS . 'settings.yml';
        if (!file_exists($file)) return null;
        
        $data = self::loadYaml($file);
        return self::getNestedValue($data, $key);
    }

    private static function putToGlobal(string $key, mixed $value): void
    {
        $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
        $data = self::loadYaml($file);
        if (!isset($data['settings'])) $data['settings'] = [];
        self::setNestedValue($data['settings'], $key, $value);
        self::saveYaml($file, $data);
    }

    private static function putToSys(string $key, mixed $value): void
    {
        $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
        $data = self::loadYaml($file);
        self::setNestedValue($data, $key, $value);
        self::saveYaml($file, $data);
    }

    private static function putToApp(string $key, mixed $value, string $appname): void
    {
        $app = \SPP\App::getApp($appname);
        $file = $app->getAppConfDir() . SPP_DS . 'settings.yml';
        $data = self::loadYaml($file);
        self::setNestedValue($data, $key, $value);
        self::saveYaml($file, $data);
    }

    private static function getFromDb(string $key, string $appname): mixed
    {
        if (class_exists('\\SPPMod\\DBSettings\\DBSettings')) {
             return \SPPMod\DBSettings\DBSettings::get($key, $appname);
        }
        return null;
    }

    private static function putToDb(string $key, mixed $value, string $appname): void
    {
        if (class_exists('\\SPPMod\\DBSettings\\DBSettings')) {
             \SPPMod\DBSettings\DBSettings::set($key, $value, $appname);
        }
    }

    // --- Performance Caching Layer ---

    private static function getCompiledPath(string $appname): string
    {
        return SPP_APP_DIR . SPP_DS . 'var' . SPP_DS . 'cache' . SPP_DS . "config_{$appname}.php";
    }

    private static function loadCompiled(string $appname): array
    {
        static $compiledData = [];
        if (isset($compiledData[$appname])) return $compiledData[$appname];

        $path = self::getCompiledPath($appname);
        if (file_exists($path)) {
            $compiledData[$appname] = require $path;
            return $compiledData[$appname];
        }
        return [];
    }

    public static function compile(string $appname): void
    {
        $all = [];
        
        // Flatten global-settings (sys and global)
        $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
        if (file_exists($file)) {
            $data = self::loadYaml($file);
            // Sys keys
            foreach ($data as $k => $v) {
                if ($k === 'settings') continue;
                $all["sys:{$k}"] = $v;
                $all[$k] = $v; // Generic fallback
            }
            // Global keys
            if (isset($data['settings'])) {
                foreach ($data['settings'] as $k => $v) {
                    $all["global:{$k}"] = $v;
                    $all[$k] = $v; // Generic fallback
                }
            }
        }

        // Flatten App settings
        try {
            $app = \SPP\App::getApp($appname);
            $appFile = $app->getAppConfDir() . SPP_DS . 'settings.yml';
            if (file_exists($appFile)) {
                $appData = self::loadYaml($appFile);
                foreach ($appData as $k => $v) {
                    $all["app:{$k}"] = $v;
                    $all[$k] = $v; // Overrides global in generic lookup
                }
            }
        } catch (\Exception $e) {}

        $path = self::getCompiledPath($appname);
        $content = "<?php\nreturn " . var_export($all, true) . ";\n";
        
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($path, $content);
    }

    public static function clearCompiled(string $appname): void
    {
        $path = self::getCompiledPath($appname);
        if (file_exists($path)) @unlink($path);
    }

    // --- Helpers ---

    private static function loadYaml(string $file): array
    {
        if (!file_exists($file)) return [];
        try {
            return Yaml::parseFile($file) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function saveYaml(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($file, Yaml::dump($data, 4, 4));
    }

    private static function getNestedValue(array $data, string $key): mixed
    {
        if ($key === '') return $data;
        $parts = explode('.', $key);
        $curr = $data;
        foreach ($parts as $p) {
            if (is_array($curr) && array_key_exists($p, $curr)) {
                $curr = $curr[$p];
            } else {
                return null;
            }
        }
        return $curr;
    }

    private static function setNestedValue(array &$data, string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $curr = &$data;
        foreach ($parts as $p) {
            if (!is_array($curr)) $curr = [];
            if (!isset($curr[$p])) $curr[$p] = [];
            $curr = &$curr[$p];
        }
        $curr = $value;
    }
}
