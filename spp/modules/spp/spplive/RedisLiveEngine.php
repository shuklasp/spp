<?php
namespace SPPMod\SPPLive;

class RedisLiveEngine implements LiveEngineInterface {
    private $redis;
    private array $currentRequestEvents = [];

    public function __construct() {
        if (class_exists('Redis')) {
            $this->redis = new \Redis();
            try {
                // Connect with 0.5s timeout so unavailable Redis does not hang requests
                $this->redis->connect('127.0.0.1', 6379, 0.5);
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
        $eventData = [
            'target' => $componentId,
            'name' => $event,
            'params' => $params,
            'topic' => $topic
        ];
        $this->currentRequestEvents[] = $eventData;

        if ($this->redis) {
            $payload = json_encode($eventData);
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
        $events = [];
        $remaining = [];
        foreach ($this->currentRequestEvents as $evt) {
            if (in_array($evt['topic'] ?? 'global', $topics)) {
                $events[] = $evt;
            } else {
                $remaining[] = $evt;
            }
        }
        $this->currentRequestEvents = $remaining;

        if (!empty($events)) {
            return $events;
        }

        if (!$this->redis || empty($topics)) {
            return [];
        }

        foreach ($topics as $topic) {
            $listKey = "spp_live_list:{$topic}";
            // Atomically pop items from Redis fallback list
            while ($payload = $this->redis->lPop($listKey)) {
                $events[] = json_decode($payload, true);
            }
        }
        
        return $events;
    }
}
