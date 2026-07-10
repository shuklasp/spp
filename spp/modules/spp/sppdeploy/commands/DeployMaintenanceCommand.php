<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployMaintenanceCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target || str_starts_with($target, '--') || str_starts_with($target, '-')) {
            $target = \SPPMod\SPPDeploy\Deployer\TargetConnection::getDefaultEnvironment();
        }

        $state = null;
        $apiKey = 'default_cli_key';

        foreach ($args as $arg) {
            if ($arg === '--on')
                $state = 'on';
            if ($arg === '--off')
                $state = 'off';
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
        }

        if (!$state) {
            echo "Error: Must specify --on or --off.\n";
            return;
        }

        if ($target === 'local') {
            $lockFile = dirname(SPP_BASE_DIR) . '/.maintenance';
            if ($state === 'on') {
                file_put_contents($lockFile, 'Site is undergoing manual maintenance. Please check back later.');
                echo "✅ Local maintenance mode enabled.\n";
            } else {
                if (is_file($lockFile))
                    unlink($lockFile);
                echo "✅ Local maintenance mode disabled.\n";
            }
            return;
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        echo "📡 Setting maintenance mode ({$state}) on {$target}...\n";
        $resp = $conn->setMaintenanceMode($state);

        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Failed to update maintenance mode: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        echo "✅ " . $resp['message'] . "\n";
    }

    public function getName(): string
    {
        return 'deploy:maintenance';
    }

    public function getDescription(): string
    {
        return 'Toggle manual maintenance mode on a remote target or local environment';
    }
}
