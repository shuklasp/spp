<?php
namespace SPPMod\Sppdb\Migration;

class SPPMigrationManager {
    private $db;
    private $migrationsTable = 'spp_migrations';
    private $appname;

    public function __construct(string $appname) {
        $this->db = new \SPPMod\SPPDB\SPPDB();
        $this->appname = $appname;
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->exec($sql);
    }

    public function runPending(): array {
        $executed = [];
        $files = $this->getMigrationFiles();
        $ran = $this->getRanMigrations();
        
        $pending = array_diff($files, $ran);
        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();

        foreach ($pending as $file) {
            $this->runMigration($file, 'up');
            $this->logMigration($file, $batch);
            $executed[] = $file;
        }

        return $executed;
    }

    public function rollback(): array {
        $rolledBack = [];
        $lastBatch = $this->getLastBatchNumber();
        if ($lastBatch === 0) {
            return [];
        }

        $migrations = $this->getMigrationsByBatch($lastBatch);
        // Rollback in reverse order
        $migrations = array_reverse($migrations);

        foreach ($migrations as $migration) {
            $this->runMigration($migration, 'down');
            $this->deleteMigrationLog($migration);
            $rolledBack[] = $migration;
        }

        return $rolledBack;
    }

    private function getMigrationFiles(): array {
        $path = SPP_APP_DIR . '/src/' . $this->appname . '/migrations';
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.php');
        $migrations = [];
        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }
        sort($migrations);
        return $migrations;
    }

    private function getRanMigrations(): array {
        $res = $this->db->execute_query("SELECT migration FROM {$this->migrationsTable}");
        return array_column($res, 'migration');
    }

    private function getNextBatchNumber(): int {
        $res = $this->db->execute_query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        return (int)($res[0]['max_batch'] ?? 0) + 1;
    }

    private function getLastBatchNumber(): int {
        $res = $this->db->execute_query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        return (int)($res[0]['max_batch'] ?? 0);
    }

    private function getMigrationsByBatch(int $batch): array {
        $res = $this->db->execute_query("SELECT migration FROM {$this->migrationsTable} WHERE batch = ?", [$batch]);
        return array_column($res, 'migration');
    }

    private function logMigration(string $migration, int $batch): void {
        $this->db->execute_query("INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)", [$migration, $batch]);
    }

    private function deleteMigrationLog(string $migration): void {
        $this->db->execute_query("DELETE FROM {$this->migrationsTable} WHERE migration = ?", [$migration]);
    }

    private function runMigration(string $name, string $direction): void {
        $path = SPP_APP_DIR . '/src/' . $this->appname . '/migrations/' . $name . '.php';
        require_once $path;

        // Convert 2026_06_01_000000_create_users_table to CreateUsersTable
        $className = preg_replace('/^[0-9_]+_/', '', $name);
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $className)));
        
        $instance = new $className();
        $instance->$direction();
    }
}
