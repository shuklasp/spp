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

        if (!$moduleName) {
            echo "Usage: php spp.php module:disable <modulename>\n";
            return;
        }

        echo "🛑 Disabling module '{$moduleName}'...\n";
        try {
            $res = ModuleInstaller::setModuleStatus($moduleName, 'inactive');
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
