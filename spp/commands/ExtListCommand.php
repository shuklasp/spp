<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ExtListCommand extends Command
{
    protected string $name = 'ext:list';
    protected string $description = 'List all available and installed extensions';

    public function execute(array $args): void
    {
        echo "Installed Extensions:\n";
        echo "---------------------\n";
        
        $extDir = SPP_BASE_DIR . '/modules';
        if (is_dir($extDir)) {
            $modules = glob($extDir . '/*', GLOB_ONLYDIR);
            foreach ($modules as $module) {
                $basename = basename($module);
                echo "- $basename (Enabled)\n";
            }
        }
    }
}
