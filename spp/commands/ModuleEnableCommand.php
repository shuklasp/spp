<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\ModuleInstaller;

class ModuleEnableCommand extends Command
{
    public function getName(): string
    {
        return 'module:enable';
    }

    public function getDescription(): string
    {
        return 'Enable an SPP module';
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
            echo "Usage: php spp.php module:enable <modulename> [--app=AppName]\n";
            return;
        }

        if ($appName) {
            \SPP\Scheduler::setContext($appName);
        }

        $context = $appName ?: (\SPP\Scheduler::getContext() ?: 'default');
        echo "🚀 Enabling module '{$moduleName}' for context [{$context}]...\n";
        try {
            $res = ModuleInstaller::setModuleStatus($moduleName, 'active', $context);
            if ($res) {
                echo "✅ Module activated and cache recompiled.\n";
            } else {
                echo "❌ Failed to activate module.\n";
            }
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    }
}
