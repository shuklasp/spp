<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class StorageCleanCommand extends Command
{
    protected string $name = 'storage:clean';
    protected string $description = 'Clean up temporary files in storage';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }

        \SPP\Scheduler::withContext($appname, function() use ($appname) {
            $tempDir = SPP_APP_DIR . '/var/storage/temp';
            if (!is_dir($tempDir)) {
                echo "No temporary storage directory found at {$tempDir}.\n";
                return;
            }
            
            $files = glob($tempDir . '/*');
            $count = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
            
            echo "Cleaned {$count} temporary files from storage.\n";
        });
    }
}
