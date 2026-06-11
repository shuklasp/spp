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
 *     2.2 refactored internals
 */
class Registry extends \SPP\SPPObject
{
    /** @var array<string,mixed> Main hierarchical data store */
    public static array $reg = [];

    /** @deprecated No longer used. Kept for backwards compatibility. */
    public static array $values = [];

    /** @var array<string,string> Resolved name cache */
    private static array $resolvedNames = [];

    /** @var array<string,bool> Keys that are permanently locked from modification */
    private static array $lockedKeys = [];

    /** @var \SPP\Core\Container|null */
    private static ?\SPP\Core\Container $container = null;

    /** @var \SPP\Core\Interfaces\SharedStorageInterface|null */
    private static ?\SPP\Core\Interfaces\SharedStorageInterface $sharedStorage = null;

    /** @var bool Flag to debounce shared state writing */
    private static bool $sharedDirty = false;

    /**
     * Get the shared storage adapter.
     */
    private static function getSharedStorage(): \SPP\Core\Interfaces\SharedStorageInterface
    {
        if (self::$sharedStorage === null) {
            $redisEnabled = false;
            if (class_exists('\SPP\Module', false)) {
                $redisEnabled = \SPP\Module::getConfig('enabled', 'redis');
            }
            if (($redisEnabled === true || $redisEnabled === '1' || $redisEnabled === 'true') && class_exists('\SPP\Core\RedisCache') && \SPP\Core\RedisCache::isAvailable()) {
                try {
                    self::$sharedStorage = new \SPP\Core\RedisSharedStorage();
                } catch (\Throwable $e) {
                    self::$sharedStorage = new \SPP\Core\FileSharedStorage();
                }
            } else {
                self::$sharedStorage = new \SPP\Core\FileSharedStorage();
            }
        }
        return self::$sharedStorage;
    }

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
     * Locks an entity configuration tree to prevent future modifications.
     */
    public static function lock(string $entity): void
    {
        $entity = str_replace('.', '=>', $entity);
        $resolved = self::resolveEntityName($entity);
        self::$lockedKeys[$resolved] = true;
    }

    /**
     * Checks if a registry key operation is blocked by an active lock.
     */
    private static function checkLock(string $resolvedEntity): void
    {
        foreach (self::$lockedKeys as $lockedKey => $true) {
            if ($resolvedEntity === $lockedKey || str_starts_with($resolvedEntity, $lockedKey . '=>')) {
                throw new \RuntimeException("Registry key is locked and cannot be modified.");
            }
        }
    }

    /**
     * Registers an entity and assigns a value.
     * Also synchronizes to shared storage for polyglot support if prefix is __shared.
     */
    public static function register(string $entity, mixed $value): void
    {
        $entity = str_replace('.', '=>', $entity);
        $entity = self::resolveEntityName($entity);
        self::checkLock($entity);
        $tokens = array_map('trim', explode('=>', $entity));

        $ref = &self::$reg;
        foreach ($tokens as $token) {
            if (!is_array($ref)) {
                $ref = [];
            }
            $ref = &$ref[$token];
        }
        $ref = $value;

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
     * Writes the shared registry state to the disk/memory safely.
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

        try {
            self::getSharedStorage()->save($shared);
        } catch (\Throwable $e) {
            // Circuit Breaker: Downgrade to File storage if Redis fails mid-execution
            if (self::$sharedStorage instanceof \SPP\Core\RedisSharedStorage) {
                self::$sharedStorage = new \SPP\Core\FileSharedStorage();
                self::$sharedStorage->save($shared);
            }
        }
        
        self::$sharedDirty = false;
    }

    /**
     * Loads shared registry entries from the JSON file/memory.
     */
    public static function loadShared(): void
    {
        try {
            $data = self::getSharedStorage()->load();
        } catch (\Throwable $e) {
            // Circuit Breaker: Downgrade to File storage if Redis fails mid-execution
            if (self::$sharedStorage instanceof \SPP\Core\RedisSharedStorage) {
                self::$sharedStorage = new \SPP\Core\FileSharedStorage();
                $data = self::$sharedStorage->load();
            } else {
                $data = [];
            }
        }
        
        foreach ($data as $k => $v) {
            self::register('__shared=>' . $k, $v);
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
        return self::get($entity);
    }

    /**
     * Retrieves the value of a registered entity.
     * Returns false if entity is not registered.
     */
    public static function get(string $entity): mixed
    {
        $entity = str_replace('.', '=>', $entity);
        $entity = self::resolveEntityName($entity);
        $tokens = array_map('trim', explode('=>', $entity));

        $ref = self::$reg;
        foreach ($tokens as $token) {
            if (!is_array($ref) || !array_key_exists($token, $ref)) {
                return false;
            }
            $ref = $ref[$token];
        }

        return $ref;
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
        $entity = str_replace('.', '=>', $entity);
        $entity = self::resolveEntityName($entity);
        self::checkLock($entity);
        $tokens = array_map('trim', explode('=>', $entity));
        
        $ref = &self::$reg;
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
        self::$resolvedNames = [];
        self::$lockedKeys = [];
        self::$values = [];
    }

    /**
     * Checks if an entity is registered.
     */
    public static function isRegistered(string $entity): bool
    {
        $entity = str_replace('.', '=>', $entity);
        $entity = self::resolveEntityName($entity);
        $tokens = array_map('trim', explode('=>', $entity));

        $ref = self::$reg;
        foreach ($tokens as $token) {
            if (!is_array($ref) || !array_key_exists($token, $ref)) {
                return false;
            }
            $ref = $ref[$token];
        }

        return true;
    }

    /**
     * Resolves the entity name with context and memoizes it.
     */
    private static function resolveEntityName(string $entity): string
    {
        // System-level global keys (starting with __) should not be prefixed with application context
        if (str_starts_with($entity, '__')) {
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
