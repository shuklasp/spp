<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;

class DeployTokenRotateCommand extends Command
{
    public function isCLIOnly(): bool { return true; }

    public function execute(array $args): void
    {
        $target = $args[2] ?? null;
        if (!$target || str_starts_with($target, '--') || str_starts_with($target, '-')) {
            $target = \SPPMod\SPPDeploy\Deployer\TargetConnection::getDefaultEnvironment();
        }

        $apiKey = getenv('SPP_DEPLOY_TOKEN') ?: 'default_cli_key';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--key=')) {
                $apiKey = substr($arg, 6);
            }
        }

        echo "\n🔄 Initiating secure gateway token rotation for {$target}...\n";

        $conn = \SPPMod\SPPDeploy\Deployer\TargetConnection::resolve($target, $apiKey);

        // Generate a cryptographically secure 256-bit hex token
        $newToken = bin2hex(random_bytes(32));

        try {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::acquireDeploymentLock();

            echo "📡 Pushing new deployment token to remote gateway...\n";
            $resp = $conn->pushEnvKey('SPP_DEPLOY_TOKEN', $newToken);

            if (!isset($resp['status']) || $resp['status'] !== 'ok') {
                echo "❌ Token rotation failed on remote target: " . ($resp['message'] ?? 'Unknown error') . "\n";
                return;
            }

            echo "✅ Remote gateway token rotated successfully.\n";

            // Update local .sppdeploy.yml configuration
            $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
            if (file_exists($confFile)) {
                $content = file_get_contents($confFile);
                // Simple string replacement or addition
                if (str_contains($content, 'token: ')) {
                    $content = preg_replace('/token:\s*[^\r\n]+/', "token: {$newToken}", $content);
                } else {
                    $content .= "\ntoken: {$newToken}\n";
                }
                file_put_contents($confFile, $content);
                echo "✅ Local .sppdeploy.yml updated with new token.\n";
            } else {
                echo "⚠️ Local .sppdeploy.yml not found. Please update your token manually:\n";
                echo "   SPP_DEPLOY_TOKEN={$newToken}\n";
            }

            echo "\n🎉 Token rotation completed with zero downtime!\n\n";
        } catch (\Exception $e) {
            echo "❌ Fatal Error during token rotation: " . $e->getMessage() . "\n";
        } finally {
            \SPPMod\SPPDeploy\Deployer\TargetConnection::releaseDeploymentLock();
        }
    }

    public function getName(): string
    {
        return 'deploy:token:rotate';
    }

    public function getDescription(): string
    {
        return 'Rotate the secure deployment gateway token on both local and remote environments with zero downtime';
    }
}
