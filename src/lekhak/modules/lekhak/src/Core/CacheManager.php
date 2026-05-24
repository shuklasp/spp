<?php
namespace SPPMod\Lekhak\Core;

class CacheManager
{
    protected static array $tags = [];
    protected static bool $initialized = false;
    protected static string $cacheDir = __DIR__ . '/../../../../../storage/cache';

    public static function init()
    {
        if (self::$initialized) return;
        self::$initialized = true;

        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }

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
            header('X-Lekhak-Cache-Tags: ' . implode(' ', self::$tags));
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
        file_put_contents(self::$cacheDir . '/' . md5($cid) . '.cache', serialize($payload));
        
        // Update tag registry
        $registry = self::getTagRegistry();
        foreach ($tags as $tag) {
            $registry[$tag][$cid] = true;
        }
        self::saveTagRegistry($registry);
    }

    public static function get(string $cid)
    {
        self::init();
        $file = self::$cacheDir . '/' . md5($cid) . '.cache';
        if (file_exists($file)) {
            $payload = unserialize(file_get_contents($file));
            if ($payload) {
                // Register the tags for this request since we hit the cache
                foreach ($payload['tags'] as $tag) {
                    self::addTag($tag);
                }
                return $payload['data'];
            }
        }
        return false;
    }

    public static function invalidateTags(array $tags)
    {
        self::init();
        $registry = self::getTagRegistry();
        $cidsToClear = [];

        foreach ($tags as $tag) {
            if (isset($registry[$tag])) {
                foreach ($registry[$tag] as $cid => $true) {
                    $cidsToClear[$cid] = true;
                }
                unset($registry[$tag]);
            }
        }

        foreach (array_keys($cidsToClear) as $cid) {
            $file = self::$cacheDir . '/' . md5($cid) . '.cache';
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        self::saveTagRegistry($registry);
    }

    public static function clearAll()
    {
        self::init();
        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        $registryFile = self::$cacheDir . '/tags.registry';
        if (file_exists($registryFile)) {
            @unlink($registryFile);
        }
    }

    protected static function getTagRegistry(): array
    {
        $file = self::$cacheDir . '/tags.registry';
        if (file_exists($file)) {
            return unserialize(file_get_contents($file)) ?: [];
        }
        return [];
    }

    protected static function saveTagRegistry(array $registry)
    {
        $file = self::$cacheDir . '/tags.registry';
        file_put_contents($file, serialize($registry));
    }
}
