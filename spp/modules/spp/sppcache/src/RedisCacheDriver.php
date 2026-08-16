<?php

namespace SPP\Core;

/**
 * Class RedisCache
 * Enterprise-grade Redis wrapper for SPP.
 * Handles distributed caching and session state.
 */
class RedisCache extends \SPP\SPPObject implements CacheInterface
{
    /** @var \Redis */
    private static $instance = null;

    /** @var bool|null */
    private static $availabilityCache = null;

    /**
     * Check if Redis is usable in the current environment.
     */
    public static function isAvailable(): bool
    {
        if (self::$availabilityCache !== null) {
            return self::$availabilityCache;
        }
        if (!class_exists('\Redis')) {
            return self::$availabilityCache = false;
        }

        try {
            $redis = self::getConnection();
            self::$availabilityCache = ($redis->ping() === '+PONG' || $redis->ping() === true);
            return self::$availabilityCache;
        } catch (\Exception $e) {
            return self::$availabilityCache = false;
        }
    }

    /**
     * Get a connected Redis instance.
     *
     * @param string $type The usage type (e.g., 'cache', 'audit') to determine DB index.
     * @return \Redis
     */
    public static function getConnection(string $type = 'cache'): \Redis
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = \SPP\Module::getConfig('host', 'redis') ?: '127.0.0.1';
        $port = \SPP\Module::getConfig('port', 'redis') ?: 6379;
        $password = \SPP\Module::getConfig('password', 'redis');

        // Logic: "store redis data according to config file setting in same db by default,
        // but in different db if set in the config file."
        $defaultDb = (int)(\SPP\Module::getConfig('db', 'redis') ?: 0);
        $specificDb = \SPP\Module::getConfig($type . '_db', 'redis');
        $dbIndex = ($specificDb !== false) ? (int)$specificDb : $defaultDb;

        $redis = new \Redis();
        // 0.2 second timeout to prevent blocking if the server is offline
        if (!$redis->connect($host, $port, 0.2)) {
            throw new \Exception("Could not connect to Redis at {$host}:{$port}");
        }

        if ($password) {
            $redis->auth($password);
        }

        if ($dbIndex > 0) {
            $redis->select($dbIndex);
        }

        self::$instance = $redis;
        return $redis;
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $redis = self::getConnection();
        return $redis->set('spp:cache:' . $key, serialize($value), $ttl);
    }

    public function get(string $key)
    {
        $redis = self::getConnection();
        $val = $redis->get('spp:cache:' . $key);
        return ($val === false) ? null : @unserialize($val, ['allowed_classes' => false]);
    }

    public function delete(string $key): bool
    {
        $redis = self::getConnection();
        return (bool)$redis->del('spp:cache:' . $key);
    }

    public function has(string $key): bool
    {
        $redis = self::getConnection();
        return $redis->exists('spp:cache:' . $key);
    }

    public function clear(): bool
    {
        $redis = self::getConnection();
        // Safe clearing: scan keys with spp:cache:* and spp:tag:* prefix
        foreach (['spp:cache:*', 'spp:tag:*'] as $pattern) {
            $iterator = null;
            while (true) {
                $keys = $redis->scan($iterator, $pattern, 1000);
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                if ($iterator === 0 || $iterator === null || $iterator === false) {
                    break;
                }
            }
        }
        return true;
    }

    public function setWithTags(string $key, $value, array $tags, int $ttl = 3600): bool
    {
        $redis = self::getConnection();
        $result = $redis->set('spp:cache:' . $key, serialize($value), $ttl);
        foreach ($tags as $tag) {
            $redis->sAdd('spp:tag:' . $tag, 'spp:cache:' . $key);
        }
        return $result;
    }

    public function invalidateTag(string $tag): bool
    {
        $redis = self::getConnection();
        $members = $redis->sMembers('spp:tag:' . $tag);
        if (!empty($members)) {
            foreach (array_chunk($members, 1000) as $chunk) {
                $redis->del($chunk);
            }
        }
        $redis->del('spp:tag:' . $tag);
        return true;
    }

    public function getWithLock(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $redis = self::getConnection();
        $lockKey = 'spp:lock:' . $key;
        if ($redis->set($lockKey, 1, ['nx', 'ex' => 10])) {
            $value = $this->get($key);
            if ($value === null) {
                $value = $callback();
                $this->set($key, $value, $ttl);
            }
            $redis->del($lockKey);
            return $value;
        }
        // Fallback if lock is held by another process
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function prune(): bool
    {
        // Redis handles key expiry natively via TTLs
        return true;
    }

    public function stats(): array
    {
        $redis = self::getConnection();
        $info = $redis->info();
        return [
            'driver' => 'RedisCache',
            'connected' => true,
            'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
            'total_keys' => $redis->dbSize()
        ];
    }
}
