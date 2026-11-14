<?php
namespace SPPMod\SPPDeploy\Scanner;

class DbScanner
{
    public function scan(?array $excludeTables = null): array
    {
        if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return [];
        }

        try {
            $db = new \SPPMod\SPPDB\SPPDB();
            $pdo = $db->getPDO();
        } catch (\Throwable $e) {
            return [];
        }

        if (!$pdo) {
            return [];
        }

        $hashes = [];
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $defaultExcludes = ['sqlite_sequence', '_shadow_*', 'parikshak_*'];
        $allExcludes = array_merge($defaultExcludes, $excludeTables ?? []);

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table'");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $tableName = $row['name'] ?? '';
                if ($this->isExcluded($tableName, $allExcludes)) {
                    continue;
                }
                $hashes[$tableName] = hash('md5', $row['sql'] ?? '');
            }
        } elseif ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                $tableName = $row[0];
                if ($this->isExcluded($tableName, $allExcludes)) {
                    continue;
                }
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch(\PDO::FETCH_ASSOC);
                $hashes[$tableName] = hash('md5', $createStmt['Create Table'] ?? '');
            }
        }
        return $hashes;
    }

    private function isExcluded(string $tableName, array $excludes): bool
    {
        foreach ($excludes as $pattern) {
            if (fnmatch($pattern, $tableName, FNM_CASEFOLD)) {
                return true;
            }
        }
        return false;
    }
}
