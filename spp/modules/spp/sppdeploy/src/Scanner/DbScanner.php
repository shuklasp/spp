<?php
namespace SPPMod\SPPDeploy\Scanner;

class DbScanner
{
    public function scan(): array
    {
        if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return [];
        }

        $db = new \SPPMod\SPPDB\SPPDB();
        $pdo = $db->getPDO();
        if (!$pdo) {
            return [];
        }

        $hashes = [];
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table'");
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $hashes[$row['name']] = hash('md5', $row['sql'] ?? '');
            }
        } elseif ($driver === 'mysql') {
            $stmt = $pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
                $tableName = $row[0];
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`")->fetch(\PDO::FETCH_ASSOC);
                $hashes[$tableName] = hash('md5', $createStmt['Create Table'] ?? '');
            }
        }
        return $hashes;
    }
}
