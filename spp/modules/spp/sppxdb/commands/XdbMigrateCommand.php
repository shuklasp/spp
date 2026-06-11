<?php

namespace SPPMod\SPPXDB\Commands;

use SPP\CLI\Command;
use SPPMod\SPPXDB\SPP_XDB;
use SPPMod\SPPXDB\MigrationManager;

/**
 * Class XdbMigrateCommand
 * Runs or rolls back SPP_XDB Migrations.
 */
class XdbMigrateCommand extends Command
{
    public function getName(): string
    {
        return 'xdb:migrate';
    }

    public function getDescription(): string
    {
        return 'Run SPP_XDB Database Migrations';
    }

    public function execute(array $args): void
    {
        $rollback = false;
        $steps = 1;
        
        foreach ($args as $arg) {
            if ($arg === '--rollback') {
                $rollback = true;
            } elseif (str_starts_with($arg, '--steps=')) {
                $steps = (int) substr($arg, 8);
            }
        }

        $db = new SPP_XDB();
        $mgr = new MigrationManager($db);

        if ($rollback) {
            echo "Rolling back $steps migration(s)...\n";
            $count = $mgr->rollback($steps);
            echo "Successfully rolled back $count migrations.\n";
        } else {
            echo "Running pending migrations...\n";
            $count = $mgr->migrate();
            if ($count === 0) {
                echo "Nothing to migrate.\n";
            } else {
                echo "Successfully executed $count migrations.\n";
            }
        }
    }
}
