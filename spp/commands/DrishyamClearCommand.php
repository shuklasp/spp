<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DrishyamClearCommand extends Command
{
    protected string $name = 'drishyam:clear';
    protected string $description = 'Clear the Drishyam rendering cache';

    public function execute(array $args): void
    {
        echo "Clearing Drishyam view cache...\n";
        
        $cacheDir = SPP_APP_DIR . '/var/storage/temp/views';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $count = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
            echo "Cleaned {$count} cached view files.\n";
        } else {
            echo "No view cache directory found at {$cacheDir}.\n";
        }
    }
}
