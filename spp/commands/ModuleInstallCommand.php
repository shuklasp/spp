<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\ModuleInstaller;

class ModuleInstallCommand extends Command
{
    public function getName(): string
    {
        return 'module:install';
    }

    public function getDescription(): string
    {
        return 'Install or upgrade a specific module or all active modules';
    }

    public function execute(array $args): void
    {
        $all = in_array('--all', $args);
        
        $moduleName = null;
        foreach ($args as $arg) {
            if ($arg !== 'module:install' && $arg !== '--all' && strpos($arg, '-') !== 0 && !strpos($arg, '.php')) {
                $moduleName = $arg;
                break;
            }
        }

        if (!$all && !$moduleName) {
            echo "Usage: php spp.php module:install <modulename> [--all]\n";
            return;
        }

        if ($all) {
            echo "📦 Installing all active modules...\n";
            $results = ModuleInstaller::installAllActive();
            foreach ($results as $name => $res) {
                $icon = $res['success'] ? '✅' : '❌';
                echo "  {$icon} " . str_pad($name, 15) . " " . $res['message'] . "\n";
            }
        } else {
            echo "📦 Installing module '{$moduleName}'...\n";
            try {
                $res = ModuleInstaller::install($moduleName);
                if ($res) {
                    echo "✅ Module successfully installed/upgraded.\n";
                } else {
                    echo "❌ Module installation failed (returned false).\n";
                }
            } catch (\Exception $e) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
}
