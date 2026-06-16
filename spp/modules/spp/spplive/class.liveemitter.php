<?php
namespace SPPMod\SPPLive;

class LiveEmitter {
    public static function emit(string $componentId, string $event, array $params = []): void
    {
        SPPLive::getEngine()->emit($componentId, $event, $params);
    }

    /**
     * Retrieves and clears the current accumulated event queue.
     * This is typically requested by the LiveComponent JSON serializer.
     */
    public static function flushEvents(): array
    {
        return SPPLive::getEngine()->flush();
    }
}
