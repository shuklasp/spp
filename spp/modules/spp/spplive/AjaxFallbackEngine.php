<?php
namespace SPPMod\SPPLive;

class AjaxFallbackEngine implements LiveEngineInterface {
    private static array $eventQueue = [];

    public function emit(string $componentId, string $event, array $params = []): void {
        self::$eventQueue[] = [
            'target' => $componentId,
            'name' => $event,
            'params' => $params
        ];
    }

    public function flush(): array {
        $events = self::$eventQueue;
        self::$eventQueue = [];
        return $events;
    }
}
