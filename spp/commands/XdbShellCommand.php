<?php
namespace SPP\CLI\Commands;

/**
 * Class XdbShellCommand
 * Launches the interactive SPPXDB shell.
 */
class XdbShellCommand extends \SPP\CLI\Command
{
    protected string $name = 'xdb:shell';
    protected string $description = 'Launch the interactive SPPXDB shell';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $shellScript = file_exists(dirname(__DIR__) . '/modules/optional/sppxdb/xdb-shell.php')
            ? dirname(__DIR__) . '/modules/optional/sppxdb/xdb-shell.php'
            : dirname(__DIR__) . '/modules/spp/sppxdb/xdb-shell.php';
        
        if (!file_exists($shellScript)) {
            echo "Error: Interactive shell script not found at $shellScript\n";
            return;
        }

        // Run the interactive shell script in the current process
        include($shellScript);
    }
}
