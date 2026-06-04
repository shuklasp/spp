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

    public static function set(string $cid, $data, array $tags = [])
    {
        self::init();
        $payload = [
            'data' => $data,
            'tags' => $tags,
            'time' => time()
        ];
        // Utilize the framework's caching layer (Redis / File)
        \SPP\Cache::setWithTags($cid, $payload, $tags);
    }

    public static function get(string $cid)
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
