<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\ModuleInstaller;
use SPPMod\SPPDB\SPPDB;

class SysUpgradeCommand extends Command
{
    public function getName(): string
    {
        return 'sys:upgrade';
    }

    public function getDescription(): string
    {
        return 'Synchronize the database schema incrementally from all active module definitions (db.yml)';
    }

    public function execute(array $args): void
    {
        echo "🚀 Starting System Upgrade...\n";

        if (!\SPP\Module::isEnabled('sppdb')) {
            echo "❌ Error: sppdb module is not enabled. Upgrades cannot run.\n";
            return;
        }

        try {
            $db = new SPPDB();
            echo "✅ Connected to Database.\n";

            // Ensure system tables first
            ModuleInstaller::setupSystemTables();

            \SPP\Module::loadAllModules();
            $modules = \SPP\Registry::get('__mods') ?? [];
            
            $count = 0;
            foreach ($modules as $modName => $modPath) {
                $module = \SPP\Module::getModule($modName);
                if ($module) {
                    $dbFile = $module->ModPath . DIRECTORY_SEPARATOR . 'db.yml';
                    if (file_exists($dbFile)) {
                        echo "📦 Synchronizing schema for module '{$modName}'...\n";
                        ModuleInstaller::executeDbYml($module);
                        $count++;
                    }
                }
            }

            echo "✅ System upgrade completed successfully. {$count} modules synchronized.\n";
        } catch (\Exception $e) {
            echo "❌ Upgrade Failed: " . $e->getMessage() . "\n";
        }
    }
}
