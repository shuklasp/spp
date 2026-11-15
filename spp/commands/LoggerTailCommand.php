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

        $linesOption = $this->getOption($args, 'lines') ?? ($args['lines'] ?? $this->getArgument($args, 0));
        $lineCount = 20;
        if ($linesOption !== null && is_numeric($linesOption)) {
            $lineCount = max(1, min(500, (int)$linesOption));
        }

        echo "Tailing log file: $logFile (last {$lineCount} lines)\n";
        echo "Note: This is a static snapshot of the last {$lineCount} lines. Use 'tail -f' natively for real-time monitoring.\n\n";

        $lines = file($logFile);
        if ($lines !== false) {
            $last = array_slice($lines, -$lineCount);
            foreach ($last as $line) {
                echo trim($line) . "\n";
            }
        }
    }
}
