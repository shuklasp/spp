<?php

namespace SPP\Core;

use SPP\CLI\CommandManager;
use SPP\CLI\Console;

/**
 * Class InteractiveShell
 * Provides an interactive REPL/Shell for SPP
 */
class InteractiveShell
{
    public function run(?string $activeApp = null): void
    {
        while (true) {
            $prompt = "spp" . ($activeApp ? "@{$activeApp}" : "") . "> ";
            
            if (function_exists('readline')) {
                $line = readline($prompt);
                if ($line !== false && trim($line) !== '') {
                    readline_add_history($line);
                }
            } else {
                echo $prompt;
                $line = fgets(STDIN);
            }

            if ($line === false) {
                echo "\n";
                break; // EOF
            }

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($line === 'exit' || $line === 'quit') {
                break;
            }

            if ($line === 'help' || $line === '?') {
                $this->printHelp();
                continue;
            }

            if (strpos($line, 'app ') === 0) {
                $parts = explode(' ', $line);
                if (isset($parts[1])) {
                    $activeApp = $parts[1];
                    echo "Switched to app: {$activeApp}\n";
                }
                continue;
            }
            
            // Execute command
            $args = explode(' ', $line);
            if ($activeApp && !in_array('--app=' . $activeApp, $args)) {
                $args[] = '--app=' . $activeApp;
            }
            
            try {
                $commandName = array_shift($args);
                $result = CommandManager::execute($commandName, $args);
                if (is_array($result) && !$result['success']) {
                    echo "Command execution failed: " . ($result['error'] ?? 'Unknown error') . "\n";
                }
            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }

    private function printHelp(): void
    {
        echo "SPP Interactive Shell Built-ins:\n";
        echo "  help, ?      Show this help message\n";
        echo "  exit, quit   Exit the shell\n";
        echo "  app <name>   Switch active application context\n";
        echo "  list         List all available SPP commands\n";
        echo "\nYou can execute any SPP command directly (e.g. 'admin:legacy export_app_package').\n";
    }
}
