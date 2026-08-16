<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EventFireCommand extends Command
{
    protected string $name = 'event:fire';
    protected string $description = 'Trigger a specific event manually';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        $event = null;
        $payload = null;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            } elseif (str_starts_with($arg, '--event=')) {
                $event = substr($arg, 8);
            } elseif (str_starts_with($arg, '--payload=')) {
                $payload = json_decode(substr($arg, 10), true) ?? substr($arg, 10);
            }
        }

        if (!$event) {
            echo "Usage: php spp.php event:fire --event=<event_name> [--payload=<json>]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($event, $payload) {
            if (class_exists('\\SPP\\Core\\EventManager')) {
                echo "Firing event '{$event}'...\n";
                \SPP\SPPEvent::triggerHook($event, $payload);
                echo "Event triggered successfully.\n";
            } else {
                echo "EventManager not found.\n";
            }
        });
    }
}
