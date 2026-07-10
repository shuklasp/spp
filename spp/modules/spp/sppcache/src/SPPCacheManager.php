<?php

namespace SPP;

use SPP\Core\CacheInterface;
use SPP\Core\FileCache;
use SPP\Core\RedisCache;

/**
 * Class Cache
 * The primary facade for framework caching.
 * Automatically chooses between Redis and File drivers based on configuration and availability.
 */
class Cache extends \SPP\SPPObject
{
    /** @var CacheInterface|null */
    private static $driver = null;

    private static array $l1Cache = [];
    private static array $telemetry = ['hits' => 0, 'misses' => 0];

    /**
     * Get the active cache driver.
     *
     * @return CacheInterface
     */
    public static function driver(): CacheInterface
    {
        if (self::$driver !== null) {
            return self::$driver;
        }

        $redisEnabled = \SPP\Module::getConfig('enabled', 'redis') === true;
        $apcuEnabled = \SPP\Module::getConfig('apcu_enabled', 'cache') !== false;

        if ($redisEnabled && RedisCache::isAvailable()) {
            self::$driver = new RedisCache();
        } elseif ($apcuEnabled && \SPP\Core\APCuCache::isAvailable()) {
            self::$driver = new \SPP\Core\APCuCache();
        } else {
            // Fallback to optimized FileCache
            self::$driver = new FileCache();
        }

        return self::$driver;
    }

    /**
     * Facade methods
     */
    public static function get(string $key)
    {
        if (array_key_exists($key, self::$l1Cache)) {
            self::$telemetry['hits']++;
            return self::$l1Cache[$key];
        }

        $value = self::driver()->get($key);
        if ($value !== null) {
            self::$l1Cache[$key] = $value;
            self::$telemetry['hits']++;
        } else {
            self::$telemetry['misses']++;
        }

        return $value;
    }

    public static function set(string $key, $value, int $ttl = 3600): bool
    {
        self::$l1Cache[$key] = $value;
        return self::driver()->set($key, $value, $ttl);
    }

    public static function delete(string $key): bool
    {
        unset(self::$l1Cache[$key]);
        return self::driver()->delete($key);
    }

    public static function has(string $key): bool
    {
        if (array_key_exists($key, self::$l1Cache)) {
            return true;
        }
        return self::driver()->has($key);
    }

    public static function clear(): bool
    {
        self::$l1Cache = [];
        $systemCacheDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'system' : '';
        if ($systemCacheDir !== '' && is_dir($systemCacheDir)) {
            $files = glob($systemCacheDir . DIRECTORY_SEPARATOR . '*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
        return self::driver()->clear();
    }

    public static function setWithTags(string $key, $value, array $tags, int $ttl = 3600): bool
    {
        self::$l1Cache[$key] = $value;
        return self::driver()->setWithTags($key, $value, $tags, $ttl);
    }

    public static function invalidateTag(string $tag): bool
    {
        self::$l1Cache = [];
        return self::driver()->invalidateTag($tag);
    }

    public static function getWithLock(string $key, int $ttl, callable $callback)
    {
        if (array_key_exists($key, self::$l1Cache)) {
            self::$telemetry['hits']++;
            return self::$l1Cache[$key];
        }
        $value = self::driver()->getWithLock($key, $ttl, $callback);
        if ($value !== null) {
            self::$l1Cache[$key] = $value;
            self::$telemetry['hits']++;
        }
        return $value;
    }

    public static function prune(): bool
    {
        return self::driver()->prune();
    }

    public static function stats(): array
    {
        return [
            'l1_cache_count' => count(self::$l1Cache),
            'telemetry' => self::$telemetry,
            'driver_stats' => self::driver()->stats()
        ];
    }

    public static function getTelemetry(): array
    {
        return self::$telemetry;
    }
}
