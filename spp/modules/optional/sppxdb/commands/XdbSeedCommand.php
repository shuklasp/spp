<?php

namespace SPPMod\SPPXDB\Commands;

use SPP\CLI\Command;
use SPPMod\SPPXDB\SPP_XDB;
use SPPMod\SPPXDB\SeederManager;

/**
 * Class XdbSeedCommand
 * Runs SPP_XDB Seeders.
 */
class XdbSeedCommand extends Command
{
    public function getName(): string
    {
        return 'xdb:seed';
    }

    public function getDescription(): string
    {
        return 'Run SPP_XDB Database Seeders';
    }

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $specificSeeder = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--class=')) {
                $specificSeeder = substr($arg, 8);
            }
        }

        $db = new SPP_XDB();
        $mgr = new SeederManager($db);

        echo "Running seeders...\n";
        $count = $mgr->seed($specificSeeder);
        echo "Successfully executed $count seeders.\n";
    }
}
