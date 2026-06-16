<?php
namespace SPPMod\SPPLive;

class RedisLiveEngine implements LiveEngineInterface {
    private $redis;

    public function __construct() {
        if (class_exists('Redis')) {
            $this->redis = new \Redis();
            try {
                $this->redis->connect('127.0.0.1', 6379);
            } catch (\Exception $e) {
                // Redis offline fallback logic
                $this->redis = null;
            }
        }
    }

    public function emit(string $componentId, string $event, array $params = []): void {
        if ($this->redis) {
            $payload = json_encode([
                'target' => $componentId,
                'name' => $event,
                'params' => $params
            ]);
            $this->redis->publish("spp_live:{$componentId}", $payload);
        }
    }

    public function flush(): array {
        // Websockets stream proactively; no manual flush needed.
        return [];
    }
}
