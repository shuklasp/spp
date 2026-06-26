<?php

namespace SPPMod\SPPReport\Commands;

use SPP\CLI\Command;

/**
 * Class ReportCronCommand
 * CLI Command to trigger the SPP Report scheduled alerts manually.
 */
class ReportCronCommand extends Command
{
    protected string $name = 'sppreport:cron';
    protected string $description = 'Trigger SPP Report threshold alerts and scheduled jobs';

    public function execute(array $args): void
    {
        echo "Starting SPP Report Cron Engine...\n";

        $cronScript = dirname(__DIR__) . '/sppreport_cron.php';
        if (file_exists($cronScript)) {
            require $cronScript;
            echo "Cron execution completed successfully.\n";
        } else {
            echo "Error: Could not locate sppreport_cron.php at {$cronScript}\n";
            exit(1);
        }
    }
}
