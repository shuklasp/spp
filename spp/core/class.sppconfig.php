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

    /** @var array<string, array{getter: callable, setter: callable}> */
    private static array $providers = [];

    /**
     * Registers a custom configuration provider for a prefix.
     */
    public static function registerProvider(string $prefix, callable $getter, callable $setter): void
    {
        self::$providers[$prefix] = [
            'getter' => $getter,
            'setter' => $setter
        ];
    }

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
        if (!$schema || !isset($schema[$key])) {
            return;
        }

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
                if (isset($def['options'])) {
                    $opts = is_array($def['options']) ? array_keys($def['options']) : [];
                    if (!in_array((string)$value, $opts)) {
                        throw new \SPP\SPPException("Validation failed for '{$key}' in '{$namespace}': Invalid option. Allowed: " . implode(', ', $opts));
                    }
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
            default:
                if (isset(self::$providers[$type])) {
                    $val = call_user_func(self::$providers[$type]['getter'], implode(':', array_slice($parts, 1)), $appname, $key);
                } else {
                    // No prefix priority: app > global > sys > env
                    $val = self::getFromApp($key, $appname);
                if ($val === null) {
                    $val = self::getFromGlobal($key);
                }
                if ($val === null) {
                    $val = self::getFromSys($key);
                }
                if ($val === null) {
                    $val = self::getFromEnv($key);
                }
                }
                break;
        }

        $result = ($val !== null) ? $val : $default;
        $result = self::resolveInterpolations($result);
        
        self::$cache[$cacheKey] = $result;
        return $result;
    }

    private static function resolveInterpolations(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, 'env:')) {
            $envExp = substr($value, 4);
            $fallback = null;
            
            if (str_contains($envExp, '|')) {
                $parts = explode('|', $envExp, 2);
                $envKey = trim($parts[0]);
                $fallback = trim($parts[1]);
            } else {
                $envKey = trim($envExp);
            }

            $envVal = self::getFromEnv($envKey);
            $resolved = ($envVal !== null) ? $envVal : $fallback;

            if (is_string($resolved)) {
                $lower = strtolower($resolved);
                if ($lower === 'true') return true;
                if ($lower === 'false') return false;
                if ($lower === 'null') return null;
                if (is_numeric($resolved)) {
                    if (str_contains($resolved, '.')) {
                        return (float) $resolved;
                    } elseif (preg_match('/^-?[1-9][0-9]*$/', $resolved) || $resolved === '0') {
                        return (int) $resolved;
                    }
                }
            }
            return $resolved;
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::resolveInterpolations($v);
            }
        }
        return $value;
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
            default:
                if (isset(self::$providers[$type])) {
                    call_user_func(self::$providers[$type]['setter'], $realKey, $value, $appname, $key);
                }
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

    private static bool $envLoaded = false;

    private static function loadEnv(): void
    {
        if (self::$envLoaded) return;
        self::$envLoaded = true;

        $envFile = defined('SPP_APP_DIR') ? SPP_APP_DIR . SPP_DS . '.env' : '';
        if ($envFile && file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '#')) continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $k = trim($parts[0]);
                    $v = trim(trim($parts[1]), '"\'');
                    $_ENV[$k] = $v;
                    putenv("$k=$v");
                }
            }
        }
    }

    private static function getFromEnv(string $key): mixed
    {
        self::loadEnv();
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        return $val === false ? null : $val;
    }

    private static function getFromApp(string $key, string $appname): mixed
    {
        $app = \SPP\App::getApp($appname);
        $file = $app->getAppConfDir() . SPP_DS . 'settings.yml';
        if (!file_exists($file)) {
            return null;
        }

        $data = self::loadYaml($file);
        return self::getNestedValue($data, $key);
    }

    private static function putToGlobal(string $key, mixed $value): void
    {
        $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
        $data = self::loadYaml($file);
        if (!isset($data['settings'])) {
            $data['settings'] = [];
        }
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

    // --- Performance Caching Layer ---

    private static function getCompiledPath(string $appname): string
    {
        return SPP_APP_DIR . SPP_DS . 'var' . SPP_DS . 'cache' . SPP_DS . "config_{$appname}.php";
    }

    private static function loadCompiled(string $appname): array
    {
        static $compiledData = [];
        if (isset($compiledData[$appname])) {
            return $compiledData[$appname];
        }

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
                if ($k === 'settings') {
                    continue;
                }
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
        } catch (\Exception $e) {
        }

        $path = self::getCompiledPath($appname);
        $content = "<?php\nreturn " . var_export($all, true) . ";\n";

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    public static function clearCompiled(string $appname): void
    {
        $path = self::getCompiledPath($appname);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Retrieves all settings as a flat associative array.
     */
    public static function getAll(string $appname): array
    {
        $all = [];
        $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
        if (file_exists($file)) {
            $data = self::loadYaml($file);
            foreach ($data as $k => $v) {
                if ($k === 'settings') {
                    continue;
                }
                $all["sys:{$k}"] = $v;
            }
            if (isset($data['settings'])) {
                foreach ($data['settings'] as $k => $v) {
                    $all["global:{$k}"] = $v;
                }
            }
        }
        try {
            $app = \SPP\App::getApp($appname);
            $appFile = $app->getAppConfDir() . SPP_DS . 'settings.yml';
            if (file_exists($appFile)) {
                $appData = self::loadYaml($appFile);
                foreach ($appData as $k => $v) {
                    $all["app:{$k}"] = $v;
                }
            }
        } catch (\Exception $e) {
        }

        return $all;
    }

    /**
     * Deletes a configuration key.
     */
    public static function delete(string $key): void
    {
        $appname = \SPP\Scheduler::getContext() ?: 'default';
        $parts = explode(':', $key);
        $type = count($parts) > 1 ? $parts[0] : 'app';
        $realKey = count($parts) > 1 ? implode(':', array_slice($parts, 1)) : $key;

        switch ($type) {
            case 'app':
                $app = \SPP\App::getApp($appname);
                $file = $app->getAppConfDir() . SPP_DS . 'settings.yml';
                $data = self::loadYaml($file);
                self::unsetNestedValue($data, $realKey);
                self::saveYaml($file, $data);
                break;
            case 'global':
                $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
                $data = self::loadYaml($file);
                if (isset($data['settings'])) {
                    self::unsetNestedValue($data['settings'], $realKey);
                }
                self::saveYaml($file, $data);
                break;
            case 'sys':
                $file = SPP_ETC_DIR . SPP_DS . 'global-settings.yml';
                $data = self::loadYaml($file);
                self::unsetNestedValue($data, $realKey);
                self::saveYaml($file, $data);
                break;
        }

        unset(self::$cache["{$appname}::{$key}"]);
        if ($type === 'app' || $type === 'global' || $type === 'sys') {
            unset(self::$cache["{$appname}::{$realKey}"]);
            self::clearCompiled($appname);
        }
    }

    // --- Helpers ---

    private static function loadYaml(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        try {
            return Yaml::parseFile($file) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function saveYaml(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($file, Yaml::dump($data, 4, 4));
    }

    private static function getNestedValue(array $data, string $key): mixed
    {
        if ($key === '') {
            return $data;
        }
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
            if (!is_array($curr)) {
                $curr = [];
            }
            if (!isset($curr[$p])) {
                $curr[$p] = [];
            }
            $curr = &$curr[$p];
        }
        $curr = $value;
    }

    private static function unsetNestedValue(array &$data, string $key): void
    {
        $parts = explode('.', $key);
        $last = array_pop($parts);
        $curr = &$data;
        foreach ($parts as $p) {
            if (!is_array($curr) || !isset($curr[$p])) {
                return;
            }
            $curr = &$curr[$p];
        }
        if (is_array($curr) && isset($curr[$last])) {
            unset($curr[$last]);
        }
    }
}
