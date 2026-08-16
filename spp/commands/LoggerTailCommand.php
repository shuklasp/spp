<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class LoggerTailCommand extends Command
{
    protected string $name = 'logger:tail';
    protected string $description = 'Tail the SPP application log file';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $logFile = SPP_LOG_DIR . '/spp.log';
        if (!file_exists($logFile)) {
            echo "Log file not found at $logFile\n";
            return;
        }

        echo "Tailing log file: $logFile\n";
        echo "Note: This is a static snapshot of the last 20 lines. Use 'tail -f' natively for real-time monitoring.\n\n";

        $lines = file($logFile);
        if ($lines !== false) {
            $last = array_slice($lines, -20);
            foreach ($last as $line) {
                echo trim($line) . "\n";
            }
        }
    }
}
