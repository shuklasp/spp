<?php

namespace App\SPPDocs\Storage;

use App\SPPDocs\Contracts\IssueStorageInterface;

/**
 * StorageFactory
 * Resolves the active IssueStorageInterface instance based on project or global configuration.
 */
class StorageFactory
{
    private static array $instances = [];

    public static function create(array $project, string $issuesDir): IssueStorageInterface
    {
        $storageType = strtolower($project['storage'] ?? 'json');
        $key = $storageType . ':' . $issuesDir;

        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $projectId = $project['id'] ?? basename(dirname($issuesDir));
        if (in_array($storageType, ['mysql', 'mariadb', 'pgsql', 'postgres'])) {
            $dbConfig = $project['db_config'] ?? [];
            $dbConfig['driver'] = $storageType;
            $driver = new DatabaseStorageDriver($projectId, $dbConfig);
        } elseif ($storageType === 'sqlite') {
            $driver = new SqliteStorageDriver($issuesDir);
        } else {
            $driver = new JsonStorageDriver($issuesDir);
        }

        self::$instances[$key] = $driver;
        return $driver;
    }
}
