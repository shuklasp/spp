<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployEnvCommand extends Command
{
    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        $action = $args[3] ?? null;

        if (!$target || $action !== 'push') {
            echo "Error: Target connection URI and action required.\n";
            echo "Usage: php spp.php deploy:env <target_uri> push --key=MY_KEY --value=MY_VALUE [--key_api=YOUR_API_KEY]\n";
            return;
        }

        $apiKey = 'default_cli_key';
        $envKey = null;
        $envValue = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key_api=')) {
                $apiKey = substr($arg, 10);
            }
            if (str_starts_with($arg, '--key=')) {
                $envKey = substr($arg, 6);
            }
            if (str_starts_with($arg, '--value=')) {
                $envValue = substr($arg, 8);
            }
        }

        if (!$envKey || $envValue === null) {
            echo "Error: Must specify --key and --value.\n";
            return;
        }

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        echo "📡 Pushing environment variable '{$envKey}' to {$target}...\n";
        $resp = $conn->pushEnvKey($envKey, $envValue);

        if (!isset($resp['status']) || $resp['status'] !== 'ok') {
            echo "❌ Failed to update environment variable: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return;
        }

        echo "✅ " . $resp['message'] . "\n";
    }

    public function getName(): string
    {
        return 'deploy:env';
    }

    public function getDescription(): string
    {
        return 'Manage remote environment variables securely';
    }
}
