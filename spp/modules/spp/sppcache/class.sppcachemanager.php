<?php

namespace SPPMod\SPPCache;

class SPPCacheManager
{
    protected static array $tags = [];
    protected static bool $initialized = false;

    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // Register shutdown function to emit cache headers
        register_shutdown_function([self::class, 'emitHeaders']);
    }

    public static function addTag(string $tag)
    {
        self::init();
        if (!in_array($tag, self::$tags)) {
            self::$tags[] = $tag;
        }
    }

    public static function getTags(): array
    {
        return self::$tags;
    }

    public static function emitHeaders()
    {
        if (!empty(self::$tags) && !headers_sent()) {
            header('X-SPP-Cache-Tags: ' . implode(' ', self::$tags));
        }
    }

    public static function set(string $cid, $data, $ttlOrTags = 3600, array $tags = [])
    {
        self::init();
        $ttl = 3600;
        if (is_array($ttlOrTags)) {
            $tags = $ttlOrTags;
        } else {
            $ttl = (int) $ttlOrTags;
        }
        $payload = [
            'data' => $data,
            'tags' => $tags,
            'time' => time()
        ];
        // Utilize the framework's caching layer (Redis / File)
        \SPP\Cache::setWithTags($cid, $payload, $tags, $ttl);
    }

    public static function get(string $cid, callable $regenerator = null, $ttlOrTags = 3600, array $tags = [])
    {
        self::init();
        $payload = \SPP\Cache::get($cid);
        if ($payload && is_array($payload) && isset($payload['data'])) {
            // Register the tags for this request since we hit the cache
            foreach ($payload['tags'] as $tag) {
                self::addTag($tag);
            }
            return $payload['data'];
        }
        
        if ($regenerator === null) {
            return false;
        }
        
        $redisLockAcquired = false;
        $redis = null;
        if (extension_loaded('redis') && class_exists('\Redis')) {
            $redis = new \Redis();
            try {
                if ($redis->connect('127.0.0.1', 6379)) {
                    $redisLockAcquired = $redis->setNx('spp_cache_lock_' . md5($cid), 1);
                    if ($redisLockAcquired) {
                        $redis->expire('spp_cache_lock_' . md5($cid), 10);
                    }
                }
            } catch (\Exception $e) {
                $redisLockAcquired = false;
            }
        }

        if ($redisLockAcquired) {
            $data = call_user_func($regenerator);
            self::set($cid, $data, $ttlOrTags, $tags);
            $redis->del('spp_cache_lock_' . md5($cid));
            return $data;
        }

        $lockFile = sys_get_temp_dir() . '/spp_cache_lock_' . md5($cid) . '.lock';
        $lockHandle = fopen($lockFile, 'c');
        if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
            // Acquired lock, regenerate
            $data = call_user_func($regenerator);
            self::set($cid, $data, $ttlOrTags, $tags);
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            return $data;
        }
        
        // Lock not acquired, wait briefly and retry
        fclose($lockHandle);
        usleep(100000); // 100ms
        $payload = \SPP\Cache::get($cid);
        if ($payload && is_array($payload) && isset($payload['data'])) {
            foreach ($payload['tags'] as $tag) {
                self::addTag($tag);
            }
            return $payload['data'];
        }
        
        return false;
    }

    public static function invalidateTags(array $tags)
    {
        self::init();
        foreach ($tags as $tag) {
            \SPP\Cache::invalidateTag($tag);
        }
    }

    public static function clearAll()
    {
        self::init();
        \SPP\Cache::clear();
    }
}
