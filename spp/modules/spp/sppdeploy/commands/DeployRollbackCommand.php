<?php
namespace SPPMod\Sppdeploy\Commands;

use SPP\CLI\Command;

class DeployRollbackCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        $backupId = $args[3] ?? null;
        
        if (!$target || !$backupId) {
            echo "Error: Target connection URI and backup ID are required.\n";
            echo "Usage: php spp.php deploy:rollback <target_uri> <backup_id> [--key=YOUR_API_KEY] [--force]\n";
            return;
        }

        $apiKey = 'default_cli_key';
        $force = false;
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if ($arg === '--force' || $arg === '-y') {
                $force = true;
            }
        }
        
        $conn = \SPPMod\Sppdeploy\Deployer\TargetConnection::resolve($target, $apiKey);
        
        if (!$force) {
            echo "⚠️  WARNING: You are about to initiate a destructive rollback on {$target}.\n";
            echo "This will replace the current codebase and database with the state from: {$backupId}\n";
            echo "\n❓ Proceed with rollback? [Y/n] ";
            $handle = fopen("php://stdin", "r");
            $line = trim(fgets($handle));
            fclose($handle);
            if (strtolower($line) === 'n' || strtolower($line) === 'no') {
                echo "⛔ Rollback aborted by user.\n";
                return;
            }
        }

        echo "📡 Sending rollback command to {$target}...\n";
        $resp = $conn->executeRollback($backupId);
        
        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Rollback failed: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        echo "✅ " . $resp['message'] . "\n";
    }

    public function getName(): string
    {
        return 'deploy:rollback';
    }

    public function getDescription(): string
    {
        return 'Roll back a remote target to a specific snapshot backup ID';
    }
}
