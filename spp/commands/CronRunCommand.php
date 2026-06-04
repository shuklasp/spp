<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class CronRunCommand extends Command
{
    protected string $name = 'cron:run';
    protected string $description = 'Execute pending cron jobs manually';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            echo "Starting manual cron run for app: {$appname}...\n";
            
            if (class_exists('\\SPP\\Cron\\Scheduler')) {
                \SPP\Cron\Scheduler::run();
                echo "Cron run finished.\n";
            } else {
                echo "Cron Scheduler not found in core.\n";
            }
        });
    }
}
