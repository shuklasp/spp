<?php

namespace SPPMod\SPPMigrate\Scanner;

class DbScanner
{
    public function scan(): array
    {
        $hashes = [];

        if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return $hashes;
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $pdo = $db->getPDO();

            if (!$pdo) {
                return $hashes;
            }

            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
                $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($tables as $table) {
                    $schemaQuery = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'");
                    $schema = $schemaQuery->fetchColumn();
                    $hashes[$table] = hash('xxh3', $schema);
                }
            } else {
                // MySQL
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($tables as $table) {
                    $schemaQuery = $pdo->query("SHOW CREATE TABLE `{$table}`");
                    $row = $schemaQuery->fetch(\PDO::FETCH_ASSOC);
                    $schema = $row['Create Table'] ?? '';
                    // Clean up AUTO_INCREMENT to prevent false diffs
                    $schema = preg_replace('/AUTO_INCREMENT=\d+ /i', '', $schema);
                    $hashes[$table] = hash('xxh3', $schema);
                }
            }
        } catch (\Exception $e) {
            error_log("DbScanner error: " . $e->getMessage());
        }

        return $hashes;
    }
}
