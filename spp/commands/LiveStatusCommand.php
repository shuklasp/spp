<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LiveStatusCommand extends Command
{
    protected string $name = 'live:status';
    protected string $description = 'Check the status of websocket/polling servers';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Checking SPPLive status...\n";
        
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }
        
        \SPP\Scheduler::withContext($appname, function() {
            if (class_exists('\\SPPMod\\SPPLive\\SPPLive')) {
                echo "SPPLive module is active. Real-time connections are managed via CDC streaming endpoints.\n";
            } else {
                echo "SPPLive module is not active.\n";
            }
        });
    }
}
