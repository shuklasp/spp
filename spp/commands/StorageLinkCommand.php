<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class StorageLinkCommand extends Command
{
    protected string $name = 'storage:link';
    protected string $description = 'Create symbolic links for public storage';

    
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
            $target = SPP_APP_DIR . '/var/storage/public';
            $link = SPP_APP_DIR . '/public/storage';
            
            if (!is_dir($target)) {
                @mkdir($target, 0755, true);
            }
            
            if (file_exists($link)) {
                echo "The [public/storage] link already exists.\n";
                return;
            }
            
            if (@symlink($target, $link)) {
                echo "The [public/storage] directory has been linked.\n";
            } else {
                echo "Failed to create symlink (You might need admin privileges on Windows).\n";
            }
        });
    }
}
