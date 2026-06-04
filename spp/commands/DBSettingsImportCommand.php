<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DBSettingsImportCommand extends Command
{
    protected string $name = 'dbsettings:import';
    protected string $description = 'Import SPP module DB settings from JSON';

    public function execute(array $args): void
    {
        $file = null;
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--file=')) $file = substr($arg, 7);
            elseif (str_starts_with($arg, '--app=')) $appname = substr($arg, 6);
        }
        
        if (!$file) {
            echo "Usage: php spp.php dbsettings:import --file=settings.json [--app=<app_name>]\n";
            return;
        }

        \SPP\Scheduler::withContext($appname, function() use ($file) {
            echo "Importing DB Settings from $file...\n";
            if (class_exists('\\SPPMod\\DBSettings\\DBSettings')) {
                echo "DB Settings import logic is a stub. Implementation pending.\n";
            } else {
                echo "DBSettings module not active.\n";
            }
        });
    }
}
