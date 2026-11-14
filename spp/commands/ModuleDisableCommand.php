<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\ModuleInstaller;

class ModuleDisableCommand extends Command
{
    public function getName(): string
    {
        return 'module:disable';
    }

    public function getDescription(): string
    {
        return 'Disable an SPP module';
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
            echo "Usage: php spp.php module:disable <modulename> [--app=AppName]\n";
            return;
        }

        if (\SPP\Module::isCompulsory($moduleName)) {
            echo "❌ Error: Cannot disable compulsory core module '{$moduleName}'. It is required for the framework to boot.\n";
            return;
        }

        if ($appName) {
            \SPP\Scheduler::setContext($appName);
        }

        $context = $appName ?: (\SPP\Scheduler::getContext() ?: 'default');
        echo "🛑 Disabling module '{$moduleName}' for context [{$context}]...\n";
        try {
            $res = ModuleInstaller::setModuleStatus($moduleName, 'inactive', $context);
            if ($res) {
                echo "✅ Module deactivated and cache recompiled.\n";
            } else {
                echo "❌ Failed to deactivate module.\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}
