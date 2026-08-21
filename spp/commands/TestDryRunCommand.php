<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\CLI\CommandManager;

/**
 * Class TestDryRunCommand
 * Iterates through all registered commands and attempts a dry-run execution
 * (using --help) to verify they do not contain fatal syntax or initialization errors.
 */
class TestDryRunCommand extends Command
{
    protected string $name = 'test:dry-run';
    protected string $description = 'Dry-run all registered commands to catch syntax and initialization errors';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Starting SPP CLI Dry-Run Fuzzing Harness...\n";
        echo "===========================================\n";

        $commands = CommandManager::discover();
        $total = count($commands);
        $passed = 0;
        $failed = 0;
        $skipped = 0;

        $skipList = [
            'shell', 'tinker', 'queue:work', 'dev:server', 'db:console'
        ];

        foreach ($commands as $name => $cmd) {
            if (in_array($name, $skipList)) {
                echo "[\033[33mSKIP\033[0m] $name (Interactive/Blocking)\n";
                $skipped++;
                continue;
            }

            // make:* commands often ignore --help if they use readline interactive prompts
            // We'll run them in a subprocess with a timeout and kill them if they block,
            // but for safety in this basic dry-run, we should probably skip interactive make:* too
            if (strpos($name, 'make:') === 0) {
                echo "[\033[33mSKIP\033[0m] $name (Interactive Scaffolding)\n";
                $skipped++;
                continue;
            }

            // Run in a separate process to catch fatal errors without crashing the harness
            $commandStr = "php spp.php $name --help";
            $output = [];
            $returnVar = 0;
            
            // Redirect stderr to stdout
            exec($commandStr . " 2>&1", $output, $returnVar);

            $outputStr = implode("\n", $output);

            if ($returnVar === 0 && stripos($outputStr, 'fatal error') === false && stripos($outputStr, 'uncaught exception') === false) {
                echo "[\033[32mPASS\033[0m] $name\n";
                $passed++;
            } else {
                echo "[\033[31mFAIL\033[0m] $name\n";
                echo "       Output: " . (strlen($outputStr) > 200 ? substr($outputStr, 0, 200) . "..." : $outputStr) . "\n";
                $failed++;
            }
        }

        echo "===========================================\n";
        echo "Summary: Total: $total | Passed: $passed | Failed: $failed | Skipped: $skipped\n";
    }
}
