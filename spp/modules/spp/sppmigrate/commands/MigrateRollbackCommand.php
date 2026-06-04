<?php
namespace SPPMod\SPPMigrate\Commands;

use SPP\CLI\Command;
use SPPMod\Sppdb\Migration\SPPMigrationManager;

class MigrateRollbackCommand extends Command {
    protected string $name = 'migrate:rollback';
    protected string $description = 'Rollback the last database migration batch';

    public function execute(array $args): void {
        $context = \SPP\Scheduler::getContext();
        echo "Rolling back migrations for [{$context}]...\n";

        $manager = new SPPMigrationManager($context);
        $rolledBack = $manager->rollback();

        if (empty($rolledBack)) {
            echo "Nothing to rollback.\n";
            return;
        }

        foreach ($rolledBack as $migration) {
            echo "\033[33mRolled back:\033[0m {$migration}\n";
        }
    }
}
