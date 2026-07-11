<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\CLI\CommandManager;

/**
 * Class ShellCommand
 * Interactive SPP Shell Mode providing all functions of spp.php and more.
 */
class ShellCommand extends Command
{
    protected string $name = 'shell';
    protected string $description = 'Launch the interactive SPP Shell Mode (run all CLI commands, switch apps, inspect state, tabs, AI, polyglot, etc.).';

    public function isCLIOnly(): bool
    {
        return true;
    }

    /** @var array<string> */
    private static array $commandList = [];

    public function execute(array $args): void
    {
        echo "\n===================================================\n";
        echo "          SPP Interactive Shell Mode\n";
        echo "===================================================\n";
        echo "Welcome to the SPP developer & administrator shell.\n";
        echo "Type 'help' or '?' for shell built-ins, 'list' for SPP commands, or 'exit' to quit.\n\n";

        $activeApp = null;
        $explicitApp = $this->getOption($args, 'app');
        if ($explicitApp) {
            $activeApp = $explicitApp;
        }

        $shell = new \SPP\Core\InteractiveShell();
        $shell->run($activeApp);
    }
}
