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
        $appName = $this->getOption($args, 'app');

        if (!$moduleName) {
            echo "Usage: php spp.php module:uninstall <modulename> [--app=AppName]\n";
            return;
        }

        if (\SPP\Module::isCompulsory($moduleName)) {
            echo "❌ Error: Cannot uninstall compulsory core module '{$moduleName}'. It is required for the framework to boot.\n";
            return;
        }

        if ($appName) {
            \SPP\Scheduler::setContext($appName);
        }

        $context = $appName ?: (\SPP\Scheduler::getContext() ?: 'default');
        echo "🗑️ Uninstalling module '{$moduleName}' for context [{$context}]...\n";
        try {
            $res = ModuleInstaller::uninstall($moduleName);
            if ($res) {
                ModuleInstaller::setModuleStatus($moduleName, 'inactive', $context);
                echo "✅ Module successfully uninstalled and deactivated.\n";
            } else {
                // Force deactivation even if uninstall fails gracefully
                ModuleInstaller::setModuleStatus($moduleName, 'inactive', $context);
                echo "❌ Module uninstall failed or module not found, but it has been deactivated.\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}
