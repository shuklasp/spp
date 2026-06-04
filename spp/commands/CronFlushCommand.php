<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class CronFlushCommand extends Command
{
    protected string $name = 'cron:flush';
    protected string $description = 'Clear cron history and lock files';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() {
            $lockFile = SPP_APP_DIR . '/var/storage/temp/cron.lock';
            if (file_exists($lockFile)) {
                unlink($lockFile);
                echo "Removed cron lock file at: {$lockFile}\n";
            } else {
                echo "No cron lock file found.\n";
            }
            
            echo "Cron flush complete.\n";
        });
    }
}
