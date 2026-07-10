<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class EnvModeCommand
 * Switches the environment error reporting mode between dev (Ignition errors) and prod (500 pages).
 */
class EnvModeCommand extends Command
{
    public function execute(array $args): void
    {
        $mode = isset($args[2]) ? strtolower(trim($args[2])) : '';

        if (!in_array($mode, ['dev', 'prod'], true)) {
            echo "\n❌ Invalid or missing environment mode.\n";
            echo "👉 Usage: php spp.php env:mode <dev|prod>\n";
            echo "   - dev : Enables SPP_DEBUG=true (Expressive Ignition-style developer error pages)\n";
            echo "   - prod: Enables SPP_DEBUG=false (Clean, user-friendly 500 error pages)\n\n";
            return;
        }

        $settingsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'global-settings.yml';
        if (!file_exists($settingsPath)) {
            // Fallback for different directory structures
            $settingsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'global-settings.yml';
        }

        if (!file_exists($settingsPath)) {
            echo "\n❌ Error: global-settings.yml not found at {$settingsPath}\n";
            return;
        }

        $content = file_get_contents($settingsPath);
        if ($content === false) {
            echo "\n❌ Error: Failed to read global-settings.yml\n";
            return;
        }

        $isDebugTrue = preg_match('/debug:\s*(true|1)/i', $content);
        $isDebugFalse = preg_match('/debug:\s*(false|0)/i', $content);

        if ($mode === 'dev') {
            if ($isDebugTrue) {
                echo "\n⚡ Environment is already in DEV mode (debug: true).\n";
                return;
            } elseif ($isDebugFalse) {
                $content = preg_replace('/debug:\s*(false|0)/i', 'debug: true', $content);
            } else {
                $content .= "\ndebug: true\n";
            }
            $status = 'DEV mode (SPP_DEBUG=true)';
        } else {
            if ($isDebugFalse) {
                echo "\n🛡️ Environment is already in PROD mode (debug: false).\n";
                return;
            } elseif ($isDebugTrue) {
                $content = preg_replace('/debug:\s*(true|1)/i', 'debug: false', $content);
            } else {
                $content .= "\ndebug: false\n";
            }
            $status = 'PROD mode (SPP_DEBUG=false)';
        }

        if (file_put_contents($settingsPath, $content) !== false) {
            echo "\n✨ Successfully switched environment to {$status}!\n";
            if ($mode === 'dev') {
                echo "🚀 Expressive Ignition-style developer error pages are now active.\n\n";
            } else {
                echo "🛡️ Clean, secure, user-friendly 500 error pages are now active.\n\n";
            }
        } else {
            echo "\n❌ Error: Failed to write to global-settings.yml. Please check file permissions.\n";
        }
    }

    public function getName(): string
    {
        return 'env:mode';
    }

    public function getDescription(): string
    {
        return 'Switch environment error reporting mode between dev (Ignition errors) and prod (500 pages)';
    }

    public function isCLIOnly(): bool
    {
        return true;
    }
}
