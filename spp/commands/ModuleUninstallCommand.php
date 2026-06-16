<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\ModuleInstaller;

class ModuleUninstallCommand extends Command
{
    public function getName(): string
    {
        return 'module:uninstall';
    }

    public function getDescription(): string
    {
        return 'Uninstall a module (drops tracking but retains data tables)';
    }

    public function execute(array $args): void
    {
        $moduleName = $args[2] ?? null;

        if (!$moduleName) {
            echo "Usage: php spp.php module:uninstall <modulename>\n";
            return;
        }

        echo "🗑️ Uninstalling module '{$moduleName}'...\n";
        try {
            $res = ModuleInstaller::uninstall($moduleName);
            if ($res) {
                echo "✅ Module successfully uninstalled.\n";
            } else {
                echo "❌ Module uninstall failed or module not found.\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}
