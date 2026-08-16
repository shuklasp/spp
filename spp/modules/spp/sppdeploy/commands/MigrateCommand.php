<?php
namespace SPPMod\SPPDeploy\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\Migration\SPPMigrationManager;

class MigrateCommand extends Command
{
    public function isCLIOnly(): bool { return true; }

    protected string $name = 'migrate';
    protected string $description = 'Run pending database migrations';

    public function execute(array $args): void
    {
        $context = \SPP\Scheduler::getContext();
        echo "Running migrations for [{$context}]...\n";

        $manager = new SPPMigrationManager($context);
        $ran = $manager->runPending();

        if (empty($ran)) {
            echo "Nothing to migrate.\n";
            return;
        }

        foreach ($ran as $migration) {
            echo "\033[32mMigrated:\033[0m {$migration}\n";
        }
    }
}
