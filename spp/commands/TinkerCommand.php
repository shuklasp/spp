<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class TinkerCommand extends Command
{
    protected string $name = 'tinker';
    protected string $description = 'Interact with your application in a REPL shell.';

    public function execute(array $args): void
    {
        // Enforce Local Environment Only
        $env = getenv('APP_ENV') ?: 'local';
        if ($env !== 'local' && !in_array('--force', $args)) {
            echo "Error: Tinker is restricted to local development environments for security.\n";
            echo "Use --force if you know what you are doing.\n";
            return;
        }

        echo "SPP Interactive REPL (Tinker Mode)\n";
        echo "Type 'exit' or 'quit' to exit.\n";

        while (true) {
            echo ">>> ";
            $input = trim(fgets(STDIN));
            if ($input === 'exit' || $input === 'quit') {
                break;
            }

            if ($input === '') {
                continue;
            }

            if (substr($input, -1) !== ';' && substr($input, -1) !== '}') {
                $input .= ';';
            }

            // Attempt to treat as an expression first
            $evalInput = $input;
            if (!str_starts_with($input, 'echo') && !str_starts_with($input, 'return') && !str_starts_with($input, 'class ') && !str_starts_with($input, 'function ') && !str_starts_with($input, '$')) {
                $evalInput = 'return ' . $input;
            }

            try {
                ob_start();
                $result = eval($evalInput);
                $output = ob_get_clean();

                if ($output !== '') {
                    echo $output . "\n";
                }
                
                if ($result !== null) {
                    var_dump($result);
                }
            } catch (\ParseError $e) {
                ob_end_clean();
                // Fallback to strict eval
                try {
                    ob_start();
                    eval($input);
                    $output = ob_get_clean();
                    if ($output !== '') {
                        echo $output . "\n";
                    }
                } catch (\Throwable $e2) {
                    ob_end_clean();
                    echo "[!] " . get_class($e2) . ": " . $e2->getMessage() . "\n";
                }
            } catch (\Throwable $e) {
                ob_end_clean();
                echo "[!] " . get_class($e) . ": " . $e->getMessage() . "\n";
            }
        }
    }
}
