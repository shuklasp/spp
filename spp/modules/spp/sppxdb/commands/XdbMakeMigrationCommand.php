<?php

namespace SPPMod\SPPXDB\Commands;

use SPP\CLI\Command;
use SPPMod\SPPXDB\SPP_XDB;
use SPPMod\SPPXDB\MigrationManager;

/**
 * Class XdbMakeMigrationCommand
 * Generates a new SPP_XDB Migration stub.
 */
class XdbMakeMigrationCommand extends Command
{
    public function getName(): string
    {
        return 'xdb:make:migration';
    }

    public function getDescription(): string
    {
        return 'Create a new SPP_XDB migration file';
    }

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = null;
        
        foreach ($args as $arg) {
            if (strpos($arg, '--') === 0) continue;
            if (basename($arg) === 'spp.php' || $arg === 'spp/spp.php' || $arg === 'xdb:make:migration') continue;
            $name = $arg;
            break;
        }

        if (!$name) {
            echo "Usage: php spp.php xdb:make:migration <name_of_table>\n";
            return;
        }

        $db = new SPP_XDB();
        $mgr = new MigrationManager($db);

        $path = $mgr->create($name);
        echo "Created migration: $path\n";
    }
}
