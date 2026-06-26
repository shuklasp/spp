<?php
namespace SPPMod\Sppdeploy\Commands;

use SPP\CLI\Command;

class DeployCleanupCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target) {
            echo "Error: Target connection URI required.\n";
            echo "Usage: php spp.php deploy:cleanup <target_uri> [--keep=5] [--key=YOUR_API_KEY]\n";
            return;
        }

        $apiKey = 'default_cli_key';
        $keep = 5;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if (str_starts_with($arg, '--keep=')) {
                $keep = (int)substr($arg, 7);
            }
        }
        
        $conn = \SPPMod\Sppdeploy\Deployer\TargetConnection::resolve($target, $apiKey);
        
        echo "📡 Sending cleanup request to {$target} (Keeping latest {$keep} backups)...\n";
        $resp = $conn->cleanupBackups($keep);
        
        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Cleanup failed: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        echo "✅ " . $resp['message'] . "\n";
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
