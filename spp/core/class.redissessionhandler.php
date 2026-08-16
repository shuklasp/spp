<?php

namespace SPP\Core;



/**
 * Class RedisSessionHandler
 * Custom session handler for storing sessions in Redis.
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
    /** @var \Redis */
    private $redis;
    private $ttl;
    private $prefix = 'spp_sess:';

    public function __construct()
    {
        $this->redis = RedisCache::getConnection();
        $this->ttl = ini_get('session.gc_maxlifetime') ?: 3600;
        $this->prefix = \SPP\Module::getConfig('prefix', 'redis') ?: 'spp_sess:';
    }

    public function open($path, $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($id): string
    {
        $data = $this->redis->get($this->prefix . $id);
        file_put_contents(__DIR__ . '/redis_session.log', date('H:i:s') . " READ $id len=" . strlen((string)$data) . "\n", FILE_APPEND);
        return $data ?: '';
    }

    public function write($id, $data): bool
    {
        $res = $this->redis->setex($this->prefix . $id, $this->ttl, $data);
        file_put_contents(__DIR__ . '/redis_session.log', date('H:i:s') . " WRITE $id len=" . strlen($data) . " success=" . ($res ? 1 : 0) . " DATA=$data\n", FILE_APPEND);
        return $res;
    }

    public function destroy($id): bool
    {
        $this->redis->del($this->prefix . $id);
        file_put_contents(__DIR__ . '/redis_session.log', date('H:i:s') . " DESTROY $id\n", FILE_APPEND);
        return true;
    }

    public function gc($max_lifetime): int|false
    {
        // Redis handles expiration automatically via setex/TTL
        return 0;
    }
}
