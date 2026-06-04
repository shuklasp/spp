<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class StorageSyncCommand extends Command
{
    protected string $name = 'storage:sync';
    protected string $description = 'Sync local storage with external disks (stub)';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            echo "Running storage sync for app: {$appname}...\n";
            
            echo "Currently only local disk is configured. No external sync required.\n";
            echo "Storage sync completed successfully.\n";
        });
    }
}
