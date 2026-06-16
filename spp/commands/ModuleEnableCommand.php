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

    public function execute(array $args): void
    {
        $moduleName = $args[2] ?? null;

        if (!$moduleName) {
            echo "Usage: php spp.php module:enable <modulename>\n";
            return;
        }

        echo "🚀 Enabling module '{$moduleName}'...\n";
        try {
            $res = ModuleInstaller::setModuleStatus($moduleName, 'active');
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
