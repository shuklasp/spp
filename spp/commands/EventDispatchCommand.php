<?php

namespace SPP\CLI\Commands;

require_once __DIR__ . '/EventFireCommand.php';

class EventDispatchCommand extends EventFireCommand
{
    protected string $name = 'event:dispatch';
    protected string $description = 'Alias for event:fire';

    public function isCLIOnly(): bool
    {
        return true;
    }
}
