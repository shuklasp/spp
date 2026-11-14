<?php
declare(strict_types=1);

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\PackageManager;
use Throwable;

/**
 * Class PkgInstallCommand
 *
 * Universal Installer for native SPP Applications and Modules.
 * Downloads and installs packages from:
 * 1. Central repository (satyalab.net or configured repo)
 * 2. External links or CDNs
 * 3. GitHub release assets / git tags
 * 4. Direct HTTP/HTTPS URLs
 * 5. Local .spppkg or .zip archives
 *
 * Automatically handles App provisioning and Module provisioning (system-level or app-level).
 *
 * Usage:
 *   php spp.php pkg:install satyalab/school1
 *   php spp.php pkg:install sppmail --scope=system
 *   php spp.php pkg:install my-app-module --scope=app --app=school1
 *   php spp.php pkg:install dist/packages/school1-1.0.0.spppkg
 *   php spp.php pkg:install https://repo.satyalab.net/dist/apps/school1-1.0.0.spppkg
 */
class PkgInstallCommand extends Command
{
    protected string $name = 'pkg:install';
    protected string $description = 'Download and install a native SPP app or module from satyalab.net, URL, or file';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        if ($this->hasFlag($args, 'help') || in_array('-h', $args, true)) {
            $this->renderHelp();
            return;
        }

        $source = $this->getArgument($args, 0, '');
        if (!$source) {
            $this->error("Usage: php spp.php pkg:install <source_or_name> [options]");
            $this->line("Examples:");
            $this->line("  php spp.php pkg:install satyalab/school1");
            $this->line("  php spp.php pkg:install sppmail --scope=system");
            $this->line("  php spp.php pkg:install grading_tools --scope=app --app=school1");
            $this->line("  php spp.php pkg:install dist/packages/school1-1.0.0.spppkg");
            return;
        }

        $asName = $this->getOption($args, 'as') ?: $this->getArgument($args, 1);
        $scope = $this->getOption($args, 'scope'); // 'system' | 'app'
        $targetApp = $this->getOption($args, 'app');
        $version = $this->getOption($args, 'version');
        $repo = $this->getOption($args, 'repo', 'satyalab');
        $force = $this->hasFlag($args, 'force');

        $rootDir = defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : getcwd();
        $mgr = new PackageManager($rootDir);

        $this->info("================================================================================");
        $this->info("   📦 SPP Universal Package Installer");
        $this->info("================================================================================");
        echo "Target Package : \033[1;36m{$source}\033[0m\n";
        echo "Repository     : \033[1;33m{$repo}\033[0m\n";
        echo str_repeat("-", 80) . "\n";

        try {
            // 1. Resolve and download package
            $this->line("1. Resolving and acquiring package archive...");
            $res = $mgr->resolveAndDownload($source, [
                'version' => $version,
                'repo' => $repo
            ]);

            if (!$res['success']) {
                $this->error("❌ " . $res['message']);
                return;
            }

            $archivePath = $res['file_path'];
            $checksum = $res['checksum'] ?? 'verified';
            $this->info("✓ Package acquired: " . basename($archivePath) . " ({$checksum})");

            // 2. Install package
            $this->line("2. Extracting and provisioning package components...");
            $installResult = $mgr->installPackage($archivePath, [
                'as' => $asName,
                'scope' => $scope,
                'app' => $targetApp,
                'force' => $force
            ]);

            if (!$installResult['success']) {
                $this->error("❌ " . $installResult['message']);
                return;
            }

            $this->info("✓ " . $installResult['message']);
            echo str_repeat("-", 80) . "\n";
            $type = $installResult['type'] ?? 'app';
            $installedName = $installResult['installed_name'] ?? $source;

            if ($type === 'app') {
                echo "\033[1;32m🎉 Application '{$installedName}' is now installed and active!\033[0m\n";
                echo "Next steps:\n";
                echo "  1. Configure public domain: \033[1;33mphp spp.php server:configure --app={$installedName}\033[0m\n";
                echo "  2. Run database migration : \033[1;33mphp spp.php site:install {$installedName}\033[0m\n";
            } elseif ($type === 'module') {
                $modScope = $installResult['scope'] ?? 'system';
                echo "\033[1;32m🎉 Module '{$installedName}' ({$modScope}) is now installed and active!\033[0m\n";
            }
        } catch (Throwable $e) {
            $this->error("Installation error: " . $e->getMessage());
        }
    }

    private function renderHelp(): void
    {
        $this->line("Usage: php spp.php pkg:install <source_or_name> [options]");
        $this->line("Options:");
        $this->line("  --as=<name>           Override destination application or module name");
        $this->line("  --scope=system|app    Module scope: system (framework-wide) or app-level");
        $this->line("  --app=<appName>       Target application for app-level modules");
        $this->line("  --version=<semver>    Target specific version from central registry");
        $this->line("  --repo=<name|url>     Repository identifier or URL (default: satyalab)");
        $this->line("  --force               Overwrite existing installation if directory already exists");
    }
}
