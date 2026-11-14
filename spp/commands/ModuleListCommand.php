<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Module;

/**
 * Class ModuleListCommand
 * Discovers and tabulates active and available modules across kernel and apps.
 */
class ModuleListCommand extends Command
{
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'module:list';
    }

    public function getDescription(): string
    {
        return 'Discovers and tabulates active and available modules across kernel and apps';
    }

    public function execute(array $args): void
    {
        $appName = basename(defined('SPP_APP_DIR') ? SPP_APP_DIR : 'default');
        $filter = null;
        $statusFilter = null;
        $asJson = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appName = substr($arg, 6);
            } elseif (str_starts_with($arg, '--filter=')) {
                $filter = strtolower(substr($arg, 9));
            } elseif (str_starts_with($arg, '--status=')) {
                $statusFilter = strtolower(substr($arg, 9));
            } elseif ($arg === '--json') {
                $asJson = true;
            }
        }

        $modules = Module::listAvailableModules($appName);

        if ($filter !== null) {
            $modules = array_filter($modules, function ($m) use ($filter) {
                return str_contains(strtolower($m['name']), $filter)
                    || str_contains(strtolower($m['public_name']), $filter)
                    || str_contains(strtolower($m['module_category']), $filter);
            });
        }

        if ($statusFilter !== null) {
            $modules = array_filter($modules, function ($m) use ($statusFilter) {
                $isActive = $m['active'];
                if ($statusFilter === 'active') return $isActive;
                if ($statusFilter === 'inactive') return !$isActive;
                return true;
            });
        }

        // Sort by Category then Name
        usort($modules, function ($a, $b) {
            $catCmp = strcmp($a['module_category'], $b['module_category']);
            if ($catCmp !== 0) return $catCmp;
            return strcmp($a['name'], $b['name']);
        });

        if ($asJson) {
            echo json_encode(array_values($modules), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            return;
        }

        echo "🧩 SPP Framework Enterprise Modules (Context: {$appName})\n";
        echo str_repeat('=', 84) . "\n";
        echo str_pad("Module Name", 20) . 
             str_pad("Version", 10) . 
             str_pad("Scope", 10) . 
             str_pad("Category", 22) . 
             str_pad("Status", 12) . 
             "Compulsory\n";
        echo str_repeat('-', 84) . "\n";

        $activeCount = 0;
        $inactiveCount = 0;

        foreach ($modules as $m) {
            $name = $m['name'];
            $version = $m['version'] ?: '1.0';
            $scope = ($m['type'] === 'system') ? 'System' : 'App';
            $category = substr($m['module_category'] ?: 'General', 0, 20);
            $isActive = $m['active'];
            $isCompulsory = Module::isCompulsory($name);

            if ($isActive) {
                $activeCount++;
            } else {
                $inactiveCount++;
            }

            echo str_pad($name, 20) . 
                 str_pad($version, 10) . 
                 str_pad($scope, 10) . 
                 str_pad($category, 22) . 
                 str_pad($isActive ? 'Active' : 'Inactive', 12) . 
                 ($isCompulsory ? 'Yes' : 'No') . "\n";
        }

        echo str_repeat('=', 84) . "\n";
        $total = count($modules);
        echo "📊 Total: {$total} module(s) | Active: {$activeCount} | Inactive: {$inactiveCount}\n";
    }
}
