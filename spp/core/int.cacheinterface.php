<?php

namespace SPP\Core;

/**
 * Interface CacheInterface
 * Standard contract for framework cache drivers.
 */
interface CacheInterface
{
    /**
     * Retrieve a value from the cache.
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key);

    /**
     * Store a value in the cache.
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time-to-live in seconds.
     * @return bool
     */
    public function set(string $key, $value, int $ttl = 3600): bool;

    /**
     * Remove a value from the cache.
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Clear all values from the cache.
     * @return bool
     */
    public function clear(): bool;

    /**
     * Check if a key exists in the cache.
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Store a value in the cache and associate it with one or more tags.
     * Tags allow grouped invalidation (e.g., all entries tagged 'node:42').
     *
     * @param string   $key
     * @param mixed    $value
     * @param string[] $tags  Tags to associate with this entry.
     * @param int      $ttl   Time-to-live in seconds.
     * @return bool
     */
    public function setWithTags(string $key, $value, array $tags, int $ttl = 3600): bool;

    /**
     * Invalidate all cache entries associated with the given tag.
     *
     * @param string $tag
     * @return bool
     */
    public function invalidateTag(string $tag): bool;

    /**
     * Cache stampede protection / Mutex locking.
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    public function getWithLock(string $key, int $ttl, callable $callback);

    /**
     * Prune expired cache items.
     * @return bool
     */
    public function prune(): bool;

    /**
     * Get driver statistics and telemetry.
     * @return array
     */
    public function stats(): array;
}
