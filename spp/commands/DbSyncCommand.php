<?php
namespace SPP\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

/**
 * Class DbSyncCommand
 * CLI tool to synchronize data between any two SPPDB adapters.
 */
class DbSyncCommand extends Command
{
    public function getName(): string
    {
        return 'db:sync';
    }

    public function getDescription(): string
    {
        return 'Synchronize data between two database adapters (e.g. MySQL to XDB)';
    }

    public function execute(array $args): void
    {
        $from = $args['from'] ?? null; // e.g. mysql:users
        $to = $args['to'] ?? null;     // e.g. xdb:users_backup

        if (!$from || !$to) {
            echo "Usage: php spp.php db:sync --from=[engine:table] --to=[engine:table]\n";
            return;
        }

        list($fromEngine, $fromTable) = explode(':', $from);
        list($toEngine, $toTable) = explode(':', $to);

        echo "Syncing {$fromTable} ({$fromEngine}) -> {$toTable} ({$toEngine})...\n";

        // 1. Initialize Source
        // Note: For real use, we'd need a way to pass custom connection strings to SPPDB.
        // For this demo, we assume the environment is pre-configured.
        $source = new SPPDB(); 
        
        // 2. Initialize Target
        // We simulate a different engine by passing a custom DBURL
        $target = new SPPDB("{$toEngine}:dbname=default");

        // 3. Extract
        $data = $source->execute_query("SELECT * FROM {$fromTable}");
        $count = count($data);

        if ($count === 0) {
            echo "Source table is empty. Nothing to sync.\n";
            return;
        }

        // 4. Provision Target (Incremental)
        $schema = $source->getSchema($fromTable);
        $target->createTableIncremental($toTable, $schema['columns']);

        // 5. Load
        echo "Processing {$count} records...\n";
        foreach ($data as $row) {
            $target->insertValues($toTable, $row);
        }

        echo "Success! Sync completed.\n";
    }
}
