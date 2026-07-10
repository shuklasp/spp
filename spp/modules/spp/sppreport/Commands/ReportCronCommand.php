<?php

namespace SPPMod\SPPReport\Commands;

use SPP\CLI\Command;

/**
 * Class ReportCronCommand
 * CLI Command to trigger the SPP Report scheduled alerts and jobs securely with distributed mutex locks.
 */
class ReportCronCommand extends Command
{
    protected string $name = 'sppreport:cron';
    protected string $description = 'Trigger SPP Report threshold alerts and scheduled jobs';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "Starting SPP Report Cron Engine...\n";

        if (!class_exists('\\SPPMod\\SPPDeploy\\Deployer\\TargetConnection')) {
            $targetConnPath = dirname(__DIR__, 2) . '/sppdeploy/src/Deployer/TargetConnection.php';
            if (file_exists($targetConnPath)) {
                require_once $targetConnPath;
            }
        }

        $cronScript = dirname(__DIR__) . '/sppreport_cron.php';
        if (!file_exists($cronScript)) {
            echo "Error: Could not locate sppreport_cron.php at {$cronScript}\n";
            exit(1);
        }

        require_once $cronScript;

        try {
            if (class_exists('\\SPPMod\\SPPDeploy\\Deployer\\TargetConnection') && method_exists('\\SPPMod\\SPPDeploy\\Deployer\\TargetConnection', 'acquireDeploymentLock')) {
                \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();
            }
            
            $scheduler = new \SPPMod\SPPReport\ReportSchedulerService();
            $scheduler->runScheduledReports();

            echo "Cron execution completed successfully.\n";
        } finally {
            if (class_exists('\\SPPMod\\SPPDeploy\\Deployer\\TargetConnection') && method_exists('\\SPPMod\\SPPDeploy\\Deployer\\TargetConnection', 'releaseDeploymentLock')) {
                \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
            }
        }
    }
}
