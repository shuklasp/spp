<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class EnvModeCommand
 * Switches the environment profile and error reporting mode between dev (Ignition errors, enabled system portals) and prod (500 pages, disabled system portals).
 */
class EnvModeCommand extends Command
{
    public function execute(array $args): void
    {
        $mode = null !== $this->getArgument($args, 0) ? strtolower(trim($this->getArgument($args, 0))) : '';

        if (!in_array($mode, ['dev', 'prod'], true)) {
            echo "\n❌ Invalid or missing environment mode.\n";
            echo "👉 Usage: php spp.php env:mode <dev|prod>\n";
            echo "   - dev : Enables profile: dev & SPP_DEBUG=true (System portals sppadmin and sppdev are ENABLED)\n";
            echo "   - prod: Enables profile: prod & SPP_DEBUG=false (System portals sppadmin and sppdev are DISABLED)\n\n";
            return;
        }

        $settingsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'global-settings.yml';
        if (!file_exists($settingsPath)) {
            $settingsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'global-settings.yml';
        }

        if (!file_exists($settingsPath)) {
            echo "\n❌ Error: global-settings.yml not found at {$settingsPath}\n";
            return;
        }

        $raw = file_get_contents($settingsPath);
        if ($raw === false) {
            echo "\n❌ Error: Failed to read global-settings.yml\n";
            return;
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $config = Yaml::parse($raw) ?: [];
        $config['profile'] = $mode;
        if (!isset($config['settings'])) {
            $config['settings'] = [];
        }
        $config['settings']['debug'] = ($mode === 'dev');

        $written = file_put_contents($settingsPath, Yaml::dump($config, 10, 2));

        // Synchronize settings.xml
        $xmlPath = dirname($settingsPath) . '/settings.xml';
        if (file_exists($xmlPath)) {
            $xmlContent = file_get_contents($xmlPath);
            $xmlContent = preg_replace('/<profile>.*?<\/profile>/', '<profile>' . ($mode === 'dev' ? 'dev' : 'production') . '</profile>', $xmlContent);
            file_put_contents($xmlPath, $xmlContent);
        }

        // Clear compiled configuration cache
        $cacheFile = dirname($settingsPath, 2) . '/var/cache/system/config.php';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        if ($written !== false) {
            $status = ($mode === 'dev') ? 'DEV mode (profile: dev, SPP_DEBUG=true)' : 'PROD mode (profile: prod, SPP_DEBUG=false)';
            echo "\n✨ Successfully switched environment to {$status}!\n";
            if ($mode === 'dev') {
                echo "🚀 System portals (sppadmin, sppdev) are ENABLED.\n";
                echo "🚀 Expressive Ignition-style developer error pages are active.\n\n";
            } else {
                echo "🛡️ System portals (sppadmin, sppdev) are DISABLED.\n";
                echo "🛡️ Clean, secure, user-friendly 500 error pages are active.\n\n";
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
        return 'Switch environment error reporting mode and system portals between dev and prod';
    }

    public function isCLIOnly(): bool
    {
        return true;
    }
}
