<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class EventListListenersCommand extends Command
{
    protected string $name = 'event:list-listeners';
    protected string $description = 'List all registered global event listeners';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            echo "Event listeners registered in app context: {$appname}\n";
            echo str_repeat('-', 50) . "\n";
            
            $listeners = \SPP\SPPEvent::getRegisteredListeners();
            if (empty($listeners)) {
                echo "No event listeners registered.\n";
                return;
            }
            
            foreach ($listeners as $event => $callbacks) {
                echo "Event: {$event} (Listeners: " . count($callbacks) . ")\n";
                foreach ($callbacks as $cb) {
                    $handler = $cb['handler'] ?? 'Unknown';
                    $priority = $cb['priority'] ?? 500;
                    echo "  -> {$handler} (Priority: {$priority})\n";
                }
            }
        });
    }
}
