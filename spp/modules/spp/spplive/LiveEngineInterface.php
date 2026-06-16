<?php
namespace SPPMod\SPPLive;

interface LiveEngineInterface {
    public function emit(string $componentId, string $event, array $params = []): void;
    public function flush(): array;
}
