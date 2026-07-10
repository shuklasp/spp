<?php

namespace SPP\Core;

use SPP\Core\Interfaces\SharedStorageInterface;

/**
 * Class RedisSharedStorage
 * 
 * Distributed memory-based shared storage adapter for the SPP Registry.
 * Uses Redis to sync the registry across horizontally scaled environments.
 */
class RedisSharedStorage implements SharedStorageInterface, DiskInterface
{
    /** @var \Redis */
    private $redis;
    
    /** @var string */
    private string $key;
    
    /** @var string */
    private string $prefix;

    public function __construct()
    {
        if (!class_exists('\SPP\Core\RedisCache') || !\SPP\Core\RedisCache::isAvailable()) {
            throw new \RuntimeException("RedisCache is not available for RedisSharedStorage.");
        }

        $this->redis = \SPP\Core\RedisCache::getConnection();
        
        // Use the same config retrieval approach as the session handler
        $prefix = \SPP\Module::getConfig('prefix', 'redis') ?: 'spp_sess:';
        $this->prefix = $prefix;
        $this->key = $prefix . 'registry_shared_state';
    }

    /**
     * @inheritDoc
     */
    public function save(array $data): void
    {
        try {
            $result = $this->redis->set($this->key, json_encode($data));
            if ($result === false) {
                throw new \RuntimeException("Redis returned false on set.");
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException("Redis failed to save registry state.", 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function load(): array
    {
        try {
            $data = $this->redis->get($this->key);
            if ($data === false) {
                return [];
            }
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return [];
        } catch (\Throwable $e) {
            throw new \RuntimeException("Redis failed to load registry state.", 0, $e);
        }
    }

    protected function getStorageKey(string $path): string
    {
        return $this->prefix . 'storage:' . $path;
    }

    public function get(string $path): ?string
    {
        try {
            $data = $this->redis->get($this->getStorageKey($path));
            return $data !== false ? (string) $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function put(string $path, string $contents): bool
    {
        try {
            return $this->redis->set($this->getStorageKey($path), $contents) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function exists(string $path): bool
    {
        try {
            return (bool) $this->redis->exists($this->getStorageKey($path));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        try {
            return (bool) $this->redis->del($this->getStorageKey($path));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function readStream(string $path)
    {
        $contents = $this->get($path);
        if ($contents === null) {
            return null;
        }
        $fp = @fopen('php://temp', 'r+b');
        if ($fp !== false) {
            fwrite($fp, $contents);
            rewind($fp);
            return $fp;
        }
        return null;
    }

    public function writeStream(string $path, $resource): bool
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Argument must be a valid resource.');
        }
        $contents = stream_get_contents($resource);
        if ($contents === false) {
            return false;
        }
        return $this->put($path, $contents);
    }
}
