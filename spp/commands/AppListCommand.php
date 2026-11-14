<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class AppListCommand
 * Lists all registered SPP applications.
 */
class AppListCommand extends Command
{
    protected string $name = 'app:list';
    protected string $description = 'List all registered SPP applications';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $gsPath = SPP_BASE_DIR . '/etc/global-settings.yml';
        $settings = file_exists($gsPath) ? Yaml::parseFile($gsPath) : [];
        $registry = $settings['apps'] ?? [];
        
        $rootDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR);
        $knownPackageIds = ['cms' => 'SPPDocs', 'lekhak' => 'SPPDocs', 'samvaad' => 'SPPDocs', 'social' => 'SPPDocs', 'spp_docs' => 'SPPDocs', 'erp' => 'SPPDocs', 'vshiksha' => 'SPPDocs'];
        $appPackagesMap = [];

        // Discover packages belonging to apps
        foreach ($registry as $appName => $meta) {
            $srcPath = $meta['src_path'] ?? ('src/' . $appName);
            $fullPath = $rootDir . '/' . ltrim(str_replace('\\', '/', $srcPath), '/');
            $packagesDir = $fullPath . '/packages';
            if (is_dir($packagesDir)) {
                $appPackages = [];
                foreach (scandir($packagesDir) as $item) {
                    if ($item !== '.' && $item !== '..' && is_dir($packagesDir . '/' . $item)) {
                        $appPackages[] = $item;
                        $knownPackageIds[strtolower($item)] = $appName;
                    }
                }
                $appPackagesMap[$appName] = $appPackages;
            }
        }

        $allAppNames = [];
        foreach (array_keys($registry) as $name) {
            $meta = $registry[$name] ?? [];
            if (!empty($meta['is_package']) || !empty($meta['parent_app'])) {
                continue;
            }
            if (isset($knownPackageIds[strtolower($name)]) && strtolower($knownPackageIds[strtolower($name)]) !== strtolower($name)) {
                continue;
            }
            $allAppNames[] = $name;
        }

        echo "\nRegistered Applications:\n";
        echo str_pad("Name", 22) . str_pad("Type", 12) . str_pad("Base URL", 20) . str_pad("DB Prefix", 16) . "Packages\n";
        echo str_repeat("-", 85) . "\n";

        $isDevMode = strtolower($settings['profile'] ?? 'dev') === 'dev';
        foreach ($allAppNames as $d) {
            $meta = $registry[$d] ?? [];
            $isSystem = in_array(strtolower($d), ['default', 'sppadmin', 'sppdev', 'admin', 'system'], true) || str_starts_with($meta['src_path'] ?? '', 'spp/');
            $type = $isSystem ? 'system' : ($meta['type'] ?? 'native');
            $baseUrl = $meta['base_url'] ?? '/' . $d;
            $prefix = $meta['table_prefix'] ?? '';
            
            $baseTag = ($d === ($settings['base_app'] ?? 'default')) ? ' [BASE]' : '';
            $pkgs = $appPackagesMap[$d] ?? [];
            if (!empty($pkgs)) {
                $pkgTag = count($pkgs) . ' (' . implode(', ', $pkgs) . ')';
            } elseif (in_array(strtolower($d), ['sppadmin', 'sppdev'], true)) {
                $pkgTag = $isDevMode ? 'Enabled (Dev Mode)' : 'Disabled (Dev Mode Off)';
            } else {
                $pkgTag = '-';
            }
            
            echo str_pad($d . $baseTag, 22) . str_pad($type, 12) . str_pad($baseUrl, 20) . str_pad($prefix, 16) . $pkgTag . "\n";
        }
        echo "\n";
    }
}
