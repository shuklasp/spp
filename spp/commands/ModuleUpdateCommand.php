<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\ModuleInstaller;
use SPP\Module;

/**
 * Class ModuleUpdateCommand
 * Executes module upgrades, declarative schema synchronizations (db.yml), and lifecycle hooks.
 */
class ModuleUpdateCommand extends Command
{
    public function getName(): string
    {
        return 'module:update';
    }

    public function getDescription(): string
    {
        return 'Execute database sync, migrations, and update hooks for a module or all modules';
    }

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $target = $this->getArgument($args, 0) ?? null;
        $isAll = false;
        $fromVersion = null;
        $toVersion = null;

        foreach ($args as $arg) {
            if ($arg === '--all') $isAll = true;
            if (str_starts_with($arg, '--from=')) $fromVersion = substr($arg, 7);
            if (str_starts_with($arg, '--to=')) $toVersion = substr($arg, 5);
        }

        if (!$target && !$isAll) {
            echo "Usage: php spp.php module:update <modulename> [--from=1.0] [--to=1.1]\n";
            echo "       php spp.php module:update --all\n";
            return;
        }

        if ($target === 'all' || $isAll) {
            echo "🔄 Updating and synchronizing all active modules...\n";
            Module::loadAllModules();
            $modules = \SPP\Registry::get('__mods') ?? [];
            $count = 0;
            foreach (array_keys($modules) as $modName) {
                try {
                    echo "  📦 Updating [{$modName}]... ";
                    $res = ModuleInstaller::upgrade($modName);
                    if ($res) {
                        echo "✅ Updated.\n";
                        $count++;
                    } else {
                        echo "⚠️  Skipped (aborted by module hook).\n";
                    }
                } catch (\Throwable $e) {
                    echo "❌ Failed: " . $e->getMessage() . "\n";
                }
            }
            echo "🎉 Finished updating {$count} module(s).\n";
            return;
        }

        $moduleName = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $target));
        
        try {
            $module = Module::getModule($moduleName);
        } catch (\Throwable $e) {
            echo "❌ Error: Module '{$moduleName}' not found or could not be loaded: " . $e->getMessage() . "\n";
            return;
        }

        echo "🔄 Updating module '{$moduleName}' (Version: {$module->Version})...\n";

        try {
            $success = ModuleInstaller::upgrade($moduleName);
            if ($success) {
                echo "✅ Success: Module '{$moduleName}' updated and database schema synchronized.\n";
            } else {
                echo "⚠️  Update aborted by module lifecycle hook.\n";
            }
        } catch (\Throwable $e) {
            echo "❌ Error during module update: " . $e->getMessage() . "\n";
        }
    }
}
