<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployCleanupCommand extends Command
{
    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target || str_starts_with($target, '--') || str_starts_with($target, '-')) {
            $target = \SPPMod\SPPDeploy\Deployer\TargetConnection::getDefaultEnvironment();
        }

        $apiKey = 'default_cli_key';
        $keep = 5;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if (str_starts_with($arg, '--keep=')) {
                $keep = (int) substr($arg, 7);
            }
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        echo "📡 Sending cleanup request to {$target} (Keeping latest {$keep} backups)...\n";
        try {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();
            $resp = $conn->cleanupBackups($keep);

            if (!isset($resp['status']) || $resp['status'] !== 'ok') {
                echo "❌ Cleanup failed: " . ($resp['message'] ?? 'Unknown error') . "\n";
                return;
            }

            echo "✅ " . $resp['message'] . "\n";
        } finally {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
        }
    }

    public function getName(): string
    {
        return 'deploy:cleanup';
    }

    public function getDescription(): string
    {
        return 'Prune old rollback snapshots from the remote target server';
    }
}
