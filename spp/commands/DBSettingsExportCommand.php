<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DBSettingsExportCommand extends Command
{
    protected string $name = 'dbsettings:export';
    protected string $description = 'Export SPP module DB settings to JSON';

    public function execute(array $args): void
    {
        $appname = 'default';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appname = substr($arg, 6);
            }
        }
        
        \SPP\Scheduler::withContext($appname, function() {
            echo "Exporting DB Settings...\n";
            if (class_exists('\\SPPMod\\DBSettings\\DBSettings')) {
                echo "DB Settings export logic is a stub. Implementation pending.\n";
            } else {
                echo "DBSettings module not active.\n";
            }
        });
    }
}
