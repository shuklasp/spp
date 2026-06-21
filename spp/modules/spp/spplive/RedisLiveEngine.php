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

    public function trackPresence(string $topic, string $userId): void {
        if ($this->redis) {
            $presenceKey = "spp_live_presence:{$topic}";
            $this->redis->hSet($presenceKey, $userId, time());
            $this->redis->expire($presenceKey, 60); // Auto-expire the whole hash if idle
        }
    }

    public function emit(string $componentId, string $event, array $params = [], string $topic = 'global'): void {
        if ($this->redis) {
            $payload = json_encode([
                'target' => $componentId,
                'name' => $event,
                'params' => $params,
                'topic' => $topic
            ]);
            // Publish for WebSocket / native PubSub clients
            $this->redis->publish("spp_live_topic:{$topic}", $payload);
            
            // Push to a list for SSE/AJAX polling (capped at 50 events to save memory)
            $listKey = "spp_live_list:{$topic}";
            $this->redis->rPush($listKey, $payload);
            $this->redis->lTrim($listKey, -50, -1);
            // Auto expire the list after 10 minutes of inactivity
            $this->redis->expire($listKey, 600);
        }
    }

    public function flush(array $topics = ['global']): array {
        if (!$this->redis || empty($topics)) {
            return [];
        }

        $events = [];
        foreach ($topics as $topic) {
            $listKey = "spp_live_list:{$topic}";
            // Atomically pop all items
            while ($payload = $this->redis->lPop($listKey)) {
                $events[] = json_decode($payload, true);
            }
        }
        
        return $events;
    }
}
