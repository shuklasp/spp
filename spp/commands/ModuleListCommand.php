<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ModuleListCommand extends Command
{
    public function execute(array $args): void
    {
        echo "🧩 Indexing SPP Framework Enterprise Modules...\n";
        $modsDir = SPP_APP_DIR . '/spp/modules/spp';
        if (is_dir($modsDir)) {
            foreach (scandir($modsDir) as $m) {
                if ($m !== '.' && $m !== '..' && is_dir($modsDir . '/' . $m)) {
                    echo "  📦 Module Context: " . str_pad($m, 15) . " [Active]\n";
                }
            }
        }
        echo "================================================================================\n";
    }

    public function getName(): string
    {
        return 'module:list';
    }

    public function getDescription(): string
    {
        return 'Discovers and tabulates active kernel framework modules';
    }
}
