<?php
namespace SPPMod\SPPLive;

interface LiveEngineInterface {
    public function emit(string $componentId, string $event, array $params = [], string $topic = 'global'): void;
    public function flush(array $topics = ['global']): array;
    public function trackPresence(string $topic, string $userId): void;
}
