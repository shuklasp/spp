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
        
        $appsDir = SPP_APP_DIR . '/spp/etc/apps';
        $allAppNames = array_keys($registry);

        if (is_dir($appsDir)) {
            $dirs = scandir($appsDir);
            foreach ($dirs as $d) {
                if ($d !== '.' && $d !== '..' && is_dir($appsDir . '/' . $d)) {
                    if (!in_array($d, $allAppNames)) $allAppNames[] = $d;
                }
            }
        }

        echo "\nRegistered Applications:\n";
        echo str_pad("Name", 20) . str_pad("Type", 15) . str_pad("Base URL", 20) . "DB Prefix\n";
        echo str_repeat("-", 70) . "\n";

        foreach ($allAppNames as $d) {
            $meta = $registry[$d] ?? [];
            $type = $meta['type'] ?? 'native';
            $baseUrl = $meta['base_url'] ?? '/' . $d;
            $prefix = $meta['table_prefix'] ?? '';
            
            $baseTag = ($d === ($settings['base_app'] ?? 'default')) ? ' [BASE]' : '';
            
            echo str_pad($d . $baseTag, 20) . str_pad($type, 15) . str_pad($baseUrl, 20) . $prefix . "\n";
        }
        echo "\n";
    }
}
