<?php
namespace SPPMod\Sppdeploy\Commands;

use SPP\CLI\Command;

class DeployHistoryCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target) {
            echo "Error: Target connection URI required.\n";
            echo "Usage: php spp.php deploy:history <target_uri> [--key=YOUR_API_KEY]\n";
            return;
        }

        $apiKey = 'default_cli_key';
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
        }
        
        $conn = \SPPMod\Sppdeploy\Deployer\TargetConnection::resolve($target, $apiKey);
        
        echo "📡 Fetching deployment history from {$target}...\n";
        $resp = $conn->getHistory();
        
        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Failed to retrieve history: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        $history = $resp['history'] ?? [];
        if (empty($history)) {
            echo "ℹ️ No deployment history found.\n";
            return;
        }

        echo "\n" . str_pad("DATE", 22) . " | " . str_pad("IP", 15) . " | " . str_pad("STATUS", 10) . " | " . str_pad("FILES", 5) . " | " . str_pad("DB", 5) . " | MESSAGE\n";
        echo str_repeat("-", 90) . "\n";

        foreach ($history as $h) {
            $date = $h['timestamp'] ?? 'Unknown';
            $ip = $h['ip'] ?? 'Unknown';
            $status = $h['status'] ?? 'unknown';
            $files = $h['filesCount'] ?? 0;
            $db = $h['dbCount'] ?? 0;
            
            // Just take the first line or a short substring of the message to keep table clean
            $msg = $h['message'] ?? '';
            $msg = str_replace("\n", " ", $msg);
            if (strlen($msg) > 30) {
                $msg = substr($msg, 0, 27) . "...";
            }

            echo str_pad($date, 22) . " | " .
                 str_pad($ip, 15) . " | " .
                 str_pad($status, 10) . " | " .
                 str_pad($files, 5) . " | " .
                 str_pad($db, 5) . " | " .
                 $msg . "\n";
        }
    }

    public function getName(): string
    {
        return 'deploy:history';
    }
}
