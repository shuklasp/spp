<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDeploy\Deployer\TargetConnection;

class DeployLogsCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target) {
            echo "Error: Target connection URI required.\n";
            echo "Usage: php spp.php deploy:logs <target_uri> [--key=YOUR_API_KEY] [--tail] [--lines=100]\n";
            return;
        }

        $apiKey = 'default_cli_key';
        $tail = false;
        $lines = 100;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
            if ($arg === '--tail') {
                $tail = true;
            }
            if (str_starts_with($arg, '--lines=')) {
                $lines = (int) substr($arg, 8);
            }
        }

        $conn = TargetConnection::resolve($target, $apiKey);

        echo "📡 Fetching remote logs from {$target}...\n";

        // Initial fetch
        $resp = $conn->getLogs(-1, $lines);

        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Error fetching logs: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        echo "📄 File: " . $resp['file'] . "\n";
        echo str_repeat("-", 50) . "\n";

        if (!empty($resp['content'])) {
            echo $resp['content'];
        }

        if (!$tail) {
            return;
        }

        $offset = (int) $resp['offset'];
        echo "\n👀 Tailing log file (Press Ctrl+C to stop)...\n";
        echo str_repeat("-", 50) . "\n";

        while (true) {
            sleep(2); // Poll every 2 seconds

            $resp = $conn->getLogs($offset, 0);

            if (isset($resp['status']) && $resp['status'] === 'ok') {
                if (!empty($resp['content'])) {
                    echo $resp['content'];
                }
                $offset = (int) $resp['offset'];
            }
        }
    }

    public function getName(): string
    {
        return 'deploy:logs';
    }

    public function getDescription(): string
    {
        return 'View and tail remote application error logs securely over HTTP';
    }
}
