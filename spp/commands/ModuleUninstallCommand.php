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

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $moduleName = $this->getArgument($args, 0) ?? null;

        if (!$moduleName) {
            echo "Usage: php spp.php module:uninstall <modulename>\n";
            return;
        }

        if (\SPP\Module::isCompulsory($moduleName)) {
            echo "❌ Error: Cannot uninstall compulsory core module '{$moduleName}'. It is required for the framework to boot.\n";
            return;
        }

        echo "🗑️ Uninstalling module '{$moduleName}'...\n";
        try {
            $res = ModuleInstaller::uninstall($moduleName);
            if ($res) {
                ModuleInstaller::setModuleStatus($moduleName, 'inactive');
                echo "✅ Module successfully uninstalled and deactivated.\n";
            } else {
                // Force deactivation even if uninstall fails gracefully
                ModuleInstaller::setModuleStatus($moduleName, 'inactive');
                echo "❌ Module uninstall failed or module not found, but it has been deactivated.\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}
