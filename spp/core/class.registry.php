<?php

namespace SPP;

/**
 * class \SPP\Registry
 *
 * Implements a global registry system for Satya Portal Pack.
 * Provides hierarchical storage for application-level entities,
 * directories, classes, and functions.
 *
 * Backward-compatible modernization for PHP 8+.
 *
 * @author
 *     Satya Prakash Shukla
 * @version
 *     2.1 compatible with legacy SPP 1.x
 */
class Registry extends \SPP\SPPObject
{
    /** @var array<string,mixed> */
    public static array $reg = [];

    /** @var array<int,mixed> */
    public static array $values = [];

    /** @var array<string,mixed> Flat lookup cache for O(1) performance */
    private static array $lookupCache = [];

    /** @var array<string,string> Resolved name cache */
    private static array $resolvedNames = [];

    /** @var string Active context prefix */
    private static string $contextPrefix = '';

    /** @var int */
    private static int $valkey = 0;

    /** @var \SPP\Core\Container|null */
    private static ?\SPP\Core\Container $container = null;

    /** @var bool Flag to debounce shared state writing */
    private static bool $sharedDirty = false;

    /**
     * Get the service container instance.
     */
    public static function container(): \SPP\Core\Container
    {
        if (self::$container === null) {
            self::$container = new \SPP\Core\Container();
        }
        return self::$container;
    }

    /**
     * Bind a service to the IoC container.
     * Note: This is separate from the fast key-value store (register/get).
     */
    public static function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        self::container()->bind($abstract, $concrete, shared: $shared);
    }

    /**
     * Bind a singleton to the container.
     */
    public static function singleton(string $abstract, $concrete = null): void
    {
        self::container()->singleton($abstract, $concrete);
    }

    /**
     * Resolve a service from the IoC container.
     * Note: This is separate from the fast key-value store (register/get).
     */
    public static function make(string $abstract): mixed
    {
        return self::container()->get($abstract);
    }

    public function __construct()
    {
        // Reserved for future expansion; no initialization required.
    }

    /**
     * Registers an entity and assigns a value.
     * Also synchronizes to shared storage for polyglot support if prefix is __shared.
     */
    public static function register(string $entity, mixed $value): void
    {
        $entity = self::resolveEntityName($entity);
        $key = self::getKey($entity);

        if ($key !== false) {
            self::$values[$key] = $value;
            self::$lookupCache[$entity] = $value;
            if (str_starts_with($entity, '__shared=>')) {
                self::syncShared();
            }
            return;
        }

        // Create new hierarchical entry
        $tokens = array_map('trim', explode('=>', $entity));

        self::$values[self::$valkey] = $value;
        $arr = [array_pop($tokens) => self::$valkey];
        self::$valkey++;

        while (!empty($tokens)) {
            $val = array_pop($tokens);
            $arr = [$val => $arr];
        }

        // Merge if existing entry
        $rootKey = key($arr);
        if (array_key_exists($rootKey, self::$reg)) {
            $merged = array_merge_recursive(self::$reg[$rootKey], $arr[$rootKey]);
        } else {
            $merged = $arr[$rootKey];
        }

        self::$reg[$rootKey] = $merged;
        if (str_starts_with($entity, '__shared=>')) {
            self::syncShared();
        }
    }

    /**
     * Flags the shared registry for synchronization at shutdown.
     */
    private static function syncShared(): void
    {
        if (!self::$sharedDirty) {
            self::$sharedDirty = true;
            register_shutdown_function([self::class, 'forceSyncShared']);
        }
    }

    /**
     * Writes the shared registry state to the disk immediately.
     */
    public static function forceSyncShared(): void
    {
        if (!self::$sharedDirty) {
            return;
        }

        $shared = self::get('__shared');
        if (!is_array($shared)) {
            self::$sharedDirty = false;
            return;
        }

        $sharedDir = SPP_BASE_DIR . '/var/shared';
        if (!is_dir($sharedDir)) {
            @mkdir($sharedDir, 0777, true);
        }

        $sharedFile = $sharedDir . '/registry.json';
        @file_put_contents($sharedFile, json_encode($shared, JSON_PRETTY_PRINT));
        
        self::$sharedDirty = false;
    }

    /**
     * Loads shared registry entries from the JSON file.
     */
    public static function loadShared(): void
    {
        $sharedFile = SPP_BASE_DIR . '/var/shared/registry.json';
        if (file_exists($sharedFile)) {
            $data = json_decode(file_get_contents($sharedFile), true);
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    self::register('__shared=>' . $k, $v);
                }
            }
        }
    }

    /**
     * Registers a directory for a given category.
     */
    public static function registerDir(string $category, string|array $dir): void
    {
        $dir = str_replace('\\', '/', $dir);
        $existing = self::get('__dirs=>' . $category);
        $dirs = is_array($existing) ? $existing : [];
        $dirs = array_merge($dirs, (array) $dir);

        self::register('__dirs=>' . $category, $dirs);
    }

    /**
     * Registers a class for a given category.
     */
    public static function registerClass(string $category, string $class): void
    {
        $classes = self::get('__classes=>' . $category);
        $classes = is_array($classes) ? $classes : [];
        $classes[] = $class;

        self::register('__classes=>' . $category, $classes);
    }

    /**
     * Registers a function for a given category.
     */
    public static function registerFunction(string $category, string $function): void
    {
        $functions = self::get('__functions=>' . $category);
        $functions = is_array($functions) ? $functions : [];
        $functions[] = $function;

        self::register('__functions=>' . $category, $functions);
    }

    /**
     * Retrieves directories for a category.
     */
    public static function getDirs(string $category): array|false
    {
        return self::get('__dirs=>' . $category);
    }

    /**
     * Retrieves value of a registered entity.
     */
    public static function getValue(string $entity): mixed
    {
        $key = self::getKey($entity);
        return is_int($key) ? self::$values[$key] : false;
    }

    /**
     * Retrieves the value of a registered entity.
     * Returns false if entity is not registered.
     */
    public static function get(string $entity): mixed
    {
        $entity = self::resolveEntityName($entity);

        // O(1) Flat Cache Hit
        if (array_key_exists($entity, self::$lookupCache)) {
            return self::$lookupCache[$entity];
        }

        $key = self::getKey($entity);

        if (is_int($key)) {
            $value = self::$values[$key];
            self::$lookupCache[$entity] = $value; // Memoize for future
            return $value;
        }

        if (is_array($key)) {
            // It's a non-leaf node, attempt to resolve all children recursively
            return self::resolveValueMap($key);
        }

        return false;
    }

    /**
     * Retrieves the value as a string.
     */
    public static function getString(string $entity, string $default = ''): string
    {
        $val = self::get($entity);
        return $val !== false ? (string) $val : $default;
    }

    /**
     * Retrieves the value as an integer.
     */
    public static function getInt(string $entity, int $default = 0): int
    {
        $val = self::get($entity);
        return $val !== false ? (int) $val : $default;
    }

    /**
     * Retrieves the value as a boolean.
     */
    public static function getBool(string $entity, bool $default = false): bool
    {
        $val = self::get($entity);
        return $val !== false ? (bool) $val : $default;
    }

    /**
     * Retrieves the value as an array.
     */
    public static function getArray(string $entity, array $default = []): array
    {
        $val = self::get($entity);
        return is_array($val) ? $val : $default;
    }

    /**
     * Removes an entity from the registry.
     */
    public static function remove(string $entity): void
    {
        $entity = self::resolveEntityName($entity);
        
        // Remove from O(1) cache
        unset(self::$lookupCache[$entity]);

        $tokens = array_map('trim', explode('=>', $entity));
        $root = array_shift($tokens);
        
        if (!array_key_exists($root, self::$reg)) {
            return;
        }

        if (empty($tokens)) {
            unset(self::$reg[$root]);
            return;
        }

        $ref = &self::$reg[$root];
        foreach ($tokens as $i => $token) {
            if (!is_array($ref) || !array_key_exists($token, $ref)) {
                return;
            }
            if ($i === count($tokens) - 1) {
                unset($ref[$token]);
                return;
            }
            $ref = &$ref[$token];
        }
    }

    /**
     * Clears the entire registry.
     */
    public static function clearAll(): void
    {
        self::$reg = [];
        self::$values = [];
        self::$lookupCache = [];
        self::$valkey = 0;
    }

    /**
     * Recursively resolves an array of registry indices to their values.
     */
    private static function resolveValueMap(array $map): array
    {
        $result = [];
        foreach ($map as $k => $v) {
            if (is_int($v)) {
                $result[$k] = self::$values[$v] ?? false;
            } elseif (is_array($v)) {
                $result[$k] = self::resolveValueMap($v);
            } else {
                $result[$k] = $v;
            }
        }
        return $result;
    }

    /**
     * Checks if an entity is registered.
     */
    public static function isRegistered(string $entity): bool
    {
        $entity = self::resolveEntityName($entity);

        if (array_key_exists($entity, self::$lookupCache)) {
            return true;
        }

        return self::getKey($entity) !== false;
    }

    /**
     * Gets the registry key (internal helper).
     *
     * @param string $entity
     * @return array|int|false
     */
    private static function getKey(string $entity): array|int|false
    {
        // Internal check: if we already have the integer key mapping
        // but this is deeper than we usually cache.

        $tokens = array_map('trim', explode('=>', $entity));
        $arr = self::$reg;

        foreach ($tokens as $token) {
            if (!is_array($arr) || !array_key_exists($token, $arr)) {
                return false;
            }
            $arr = $arr[$token];
        }

        return $arr;
    }

    /**
     * Resolves the entity name with context and memoizes it.
     */
    private static function resolveEntityName(string $entity): string
    {
        // System-level global keys (starting with __) should not be prefixed with application context
        if (strpos($entity, '__') === 0) {
            return $entity;
        }

        $ctx = \SPP\Scheduler::getContext();
        if ($ctx === '' || $ctx === 'default') {
            return $entity;
        }

        $cacheKey = $ctx . '::' . $entity;
        if (isset(self::$resolvedNames[$cacheKey])) {
            return self::$resolvedNames[$cacheKey];
        }

        $resolved = '__apps=>' . $ctx . '=>' . $entity;
        self::$resolvedNames[$cacheKey] = $resolved;
        return $resolved;
    }
}
