<?php

namespace SPP\Core;

/**
 * Class APCuCache
 * APCu shared memory cache driver for SPP.
 */
class APCuCache extends \SPP\SPPObject implements CacheInterface
{
    public static function isAvailable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    public function set(string $key, $value, int $ttl = 3600): bool
    {
        return apcu_store('spp:cache:' . $key, $value, $ttl);
    }

    public function get(string $key)
    {
        $success = false;
        $val = apcu_fetch('spp:cache:' . $key, $success);
        return $success ? $val : null;
    }

    public function delete(string $key): bool
    {
        return apcu_delete('spp:cache:' . $key);
    }

    public function has(string $key): bool
    {
        return apcu_exists('spp:cache:' . $key);
    }

    public function clear(): bool
    {
        return apcu_clear_cache();
    }

    public function setWithTags(string $key, $value, array $tags, int $ttl = 3600): bool
    {
        $result = $this->set($key, $value, $ttl);
        foreach ($tags as $tag) {
            $tagKey = 'spp:tag:' . $tag;
            $success = false;
            $existing = apcu_fetch($tagKey, $success);
            if (!$success || !is_array($existing)) $existing = [];
            if (!in_array('spp:cache:' . $key, $existing)) {
                $existing[] = 'spp:cache:' . $key;
                apcu_store($tagKey, $existing);
            }
        }
        return $result;
    }

    public function invalidateTag(string $tag): bool
    {
        $tagKey = 'spp:tag:' . $tag;
        $success = false;
        $keys = apcu_fetch($tagKey, $success);
        if ($success && is_array($keys)) {
            foreach ($keys as $k) {
                apcu_delete($k);
            }
            apcu_delete($tagKey);
        }
        return true;
    }

    public function getWithLock(string $key, int $ttl, callable $callback)
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }
        $lockKey = 'spp:lock:' . $key;
        if (apcu_add($lockKey, 1, 10)) {
            $value = $this->get($key);
            if ($value === null) {
                $value = $callback();
                $this->set($key, $value, $ttl);
            }
            apcu_delete($lockKey);
            return $value;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function prune(): bool
    {
        // APCu handles TTLs natively
        return true;
    }

    public function stats(): array
    {
        $info = function_exists('apcu_cache_info') ? apcu_cache_info(true) : [];
        return [
            'driver' => 'APCuCache',
            'enabled' => self::isAvailable(),
            'num_entries' => $info['num_entries'] ?? 0,
            'mem_size_bytes' => $info['mem_size'] ?? 0
        ];
    }
}
