<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDeploy\Deployer\TargetConnection;

class DeployRunCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        $commandToRun = $args[3] ?? null;

        if ($target && !$commandToRun) {
            $commandToRun = $target;
            $target = TargetConnection::getDefaultEnvironment();
        }

        if (!$target || !$commandToRun) {
            echo "Error: Target connection URI and command are required.\n";
            echo "Usage: php spp.php deploy:run [target_uri] \"<command>\" [--key=YOUR_API_KEY]\n";
            return;
        }

        $apiKey = 'default_cli_key';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
        }

        $conn = TargetConnection::resolve($target, $apiKey);

        echo "📡 Sending secure command execution request to {$target}...\n";
        echo "   Command: {$commandToRun}\n";
        echo str_repeat("-", 50) . "\n";

        $resp = $conn->runCommand($commandToRun);

        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Error executing command: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        if (!empty($resp['output'])) {
            echo $resp['output'] . "\n";
        }

        echo str_repeat("-", 50) . "\n";
        if (isset($resp['exit_code']) && $resp['exit_code'] !== 0) {
            echo "⚠️  Command exited with non-zero code: {$resp['exit_code']}\n";
        } else {
            echo "✅ Command executed successfully.\n";
        }
    }

    public function getName(): string
    {
        return 'deploy:run';
    }

    public function getDescription(): string
    {
        return 'Securely execute an arbitrary shell command on the remote server';
    }
}
