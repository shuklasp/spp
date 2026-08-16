<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LoggerClearCommand extends Command
{
    protected string $name = 'logger:clear';
    protected string $description = 'Clear the SPP application logs';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Clearing application logs in " . SPP_LOG_DIR . "...\n";
        $files = glob(SPP_LOG_DIR . '/*.log');
        $count = 0;
        foreach ($files as $file) {
            file_put_contents($file, "");
            $count++;
        }
        echo "Cleared $count log files.\n";
    }
}
