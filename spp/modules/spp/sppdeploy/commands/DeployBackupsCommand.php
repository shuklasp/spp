<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployBackupsCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target) {
            echo "Error: Target connection URI required.\n";
            echo "Usage: php spp.php deploy:backups <target_uri> [--key=YOUR_API_KEY]\n";
            return;
        }

        $apiKey = 'default_cli_key';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        echo "📡 Fetching backups from {$target}...\n";
        $resp = $conn->getBackups();

        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Failed to retrieve backups: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        $backups = $resp['backups'] ?? [];
        if (empty($backups)) {
            echo "ℹ️ No backups found on the remote server.\n";
            return;
        }

        echo "\n" . str_pad("DATE", 22) . " | " . str_pad("ID (FILENAME)", 35) . " | SIZE\n";
        echo str_repeat("-", 75) . "\n";

        foreach ($backups as $b) {
            $date = $b['date'] ?? 'Unknown';
            $file = $b['filename'] ?? 'Unknown';
            $size = round(($b['size'] ?? 0) / 1024 / 1024, 2) . ' MB';

            echo str_pad($date, 22) . " | " .
                str_pad($file, 35) . " | " .
                $size . "\n";
        }
    }

    public function getName(): string
    {
        return 'deploy:backups';
    }

    public function getDescription(): string
    {
        return 'List available snapshot backups on a remote target for rollback';
    }
}
