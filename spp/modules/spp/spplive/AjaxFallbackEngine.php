<?php
namespace SPPMod\SPPLive;

class AjaxFallbackEngine implements LiveEngineInterface {
    private static array $eventQueue = [];

    public function trackPresence(string $topic, string $userId): void {
        // Fallback engine doesn't persist presence
    }

    public function emit(string $componentId, string $event, array $params = [], string $topic = 'global'): void {
        self::$eventQueue[] = [
            'target' => $componentId,
            'name' => $event,
            'params' => $params,
            'topic' => $topic
        ];
    }

    public function flush(array $topics = ['global']): array {
        $events = [];
        $remaining = [];
        
        foreach (self::$eventQueue as $evt) {
            if (in_array($evt['topic'] ?? 'global', $topics)) {
                $events[] = $evt;
            } else {
                $remaining[] = $evt;
            }
        }
        
        self::$eventQueue = $remaining;
        return $events;
    }
}
