<?php

namespace SPP\Core;

use SPP\Core\Interfaces\SharedStorageInterface;

/**
 * Class RedisSharedStorage
 * 
 * Distributed memory-based shared storage adapter for the SPP Registry.
 * Uses Redis to sync the registry across horizontally scaled environments.
 */
class RedisSharedStorage implements SharedStorageInterface
{
    /** @var \Redis */
    private $redis;
    
    /** @var string */
    private string $key;

    public function __construct()
    {
        if (!class_exists('\SPP\Core\RedisCache') || !\SPP\Core\RedisCache::isAvailable()) {
            throw new \RuntimeException("RedisCache is not available for RedisSharedStorage.");
        }

        $this->redis = \SPP\Core\RedisCache::getConnection();
        
        // Use the same config retrieval approach as the session handler
        $prefix = \SPP\Module::getConfig('prefix', 'redis') ?: 'spp_sess:';
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
}
