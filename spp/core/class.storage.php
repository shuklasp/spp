<?php

namespace SPP\Core;

/**
 * Class Storage
 * Facade for accessing storage disks.
 */
class Storage
{
    /** @var DiskInterface[] */
    private static array $disks = [];

    public static function disk(string $name = 'local'): DiskInterface
    {
        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        if ($name === 'file_shared') {
            $disk = new FileSharedStorage();
        } elseif ($name === 'redis_shared') {
            try {
                $disk = new RedisSharedStorage();
            } catch (\Throwable $e) {
                // Fallback to FileSharedStorage if Redis is unavailable in the environment
                $disk = new FileSharedStorage();
            }
        } elseif ($name === 'flysystem') {
            $disk = new FlysystemDisk();
        } else {
            $basePath = '';
            if (class_exists('\SPP\SPPConfig', false)) {
                $basePath = \SPP\SPPConfig::get("storage.disks.{$name}.base_path", '');
            } elseif (class_exists('\SPP\Env', false)) {
                $basePath = \SPP\Env::get('SPP_STORAGE_' . strtoupper($name) . '_PATH', '');
            }
            $disk = new LocalDisk($basePath);
        }

        self::$disks[$name] = $disk;

        return $disk;
    }

    public static function get(string $path): ?string
    {
        return self::disk()->get($path);
    }

    public static function put(string $path, string $contents): bool
    {
        return self::disk()->put($path, $contents);
    }

    public static function exists(string $path): bool
    {
        return self::disk()->exists($path);
    }

    public static function delete(string $path): bool
    {
        return self::disk()->delete($path);
    }

    public static function readStream(string $path)
    {
        return self::disk()->readStream($path);
    }

    public static function writeStream(string $path, $resource): bool
    {
        return self::disk()->writeStream($path, $resource);
    }
}
