<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPDB\SPPDB;

/**
 * Class MigrateCommand
 * Executes pending database migrations.
 */
class MigrateCommand extends Command
{
    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run database migrations for the app and all active modules';
    }

    public function execute(array $args): void
    {
        if (!\SPP\Module::isEnabled('sppdb')) {
            echo "Error: sppdb module is not enabled. Migrations cannot run.\n";
            return;
        }

        $db = new SPPDB();

        // 1. Ensure migrations tracking table exists
        $this->ensureMigrationTable($db);

        // 2. Gather migration directories
        $dirsToScan = [];

        // App migrations
        $app = $this->getContext($args);
        $appDir = SPP_APP_DIR . "/src/{$app}/migrations";
        if (is_dir($appDir)) {
            $dirsToScan[] = $appDir;
        }

        // Module migrations
        $mods = \SPP\Registry::get('__modobj');
        if (is_array($mods)) {
            foreach ($mods as $mod) {
                $modMigDir = $mod->ModPath . '/migrations';
                if (is_dir($modMigDir)) {
                    $dirsToScan[] = $modMigDir;
                }
            }
        }

        // 3. Collect and sort all migration files
        $pending = [];
        $executed = $this->getExecutedMigrations($db);

        foreach ($dirsToScan as $dir) {
            foreach (glob($dir . '/*.php') as $file) {
                $basename = basename($file, '.php');
                if (!in_array($basename, $executed)) {
                    $pending[$basename] = $file;
                }
            }
        }

        ksort($pending);

        if (empty($pending)) {
            echo "No pending migrations to run.\n";
            return;
        }

        // 4. Execute pending
        foreach ($pending as $name => $file) {
            echo "Migrating: {$name}...\n";
            require_once $file;

            $className = $name;
            if (class_exists($className)) {
                $migration = new $className();
                try {
                    $migration->up($db);
                    $this->logMigration($db, $name);
                    echo "  Done.\n";
                } catch (\Exception $e) {
                    echo "  [ERROR] Migration failed: " . $e->getMessage() . "\n";
                    return; // Stop on first failure
                }
            } else {
                echo "  [WARNING] Class {$className} not found in file {$file}\n";
            }
        }

        echo "All migrations completed successfully.\n";
    }

    private function ensureMigrationTable(SPPDB $db): void
    {
        $table = \SPPMod\SPPDB\SPPDB::sppTable('migrations');
        $isSqlite = ($db->getDriver() === 'sqlite');

        if ($isSqlite) {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $db->execute_query("CREATE TABLE IF NOT EXISTS {$table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        }
    }

    private function getExecutedMigrations(SPPDB $db): array
    {
        $table = \SPPMod\SPPDB\SPPDB::sppTable('migrations');
        $rows = $db->execute_query("SELECT migration FROM {$table}");
        $executed = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $executed[] = $row['migration'];
            }
        }
        return $executed;
    }

    private function logMigration(SPPDB $db, string $name): void
    {
        $table = \SPPMod\SPPDB\SPPDB::sppTable('migrations');
        $db->insertValues($table, ['migration' => $name]);
    }
}
