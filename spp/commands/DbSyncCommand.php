<?php
namespace SPP\CLI\Commands;

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

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $from = $this->getOption($args, 'from'); // e.g. mysql:users
        $to = $this->getOption($args, 'to');     // e.g. xdb:users_backup

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

        $fromTableEscaped = $this->escapeIdentifier($fromTable);
        $toTableEscaped = $this->escapeIdentifier($toTable);

        // 3. Extract
        $data = $source->execute_query("SELECT * FROM {$fromTableEscaped}");
        $count = count($data);

        if ($count === 0) {
            echo "Source table is empty. Nothing to sync.\n";
            return;
        }

        // 4. Provision Target (Incremental)
        $schema = $source->getSchema($fromTable);
        $target->createTableIncremental($toTableEscaped, $schema['columns']);

        // 5. Load
        echo "Processing {$count} records...\n";
        foreach ($data as $row) {
            $target->insertValues($toTableEscaped, $row);
        }

        echo "Success! Sync completed.\n";
    }
}
