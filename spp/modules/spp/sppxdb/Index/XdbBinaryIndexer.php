<?php

namespace SPPMod\SPPXDB\Index;

/**
 * XdbBinaryIndexer
 * Refines XML/SQLite indexing by replacing linear index scanning with high-performance
 * in-memory binary search over sorted index key blocks, caching active index segments.
 */
class XdbBinaryIndexer
{
    private static array $indexCache = [];

    /**
     * Builds and stores a sorted binary index block for a table column.
     *
     * @param string $dataDir Base data directory
     * @param string $tableName Table name
     * @param string $column Column to index
     * @param array $rows List of row associative arrays
     * @return bool
     */
    public static function buildIndex(string $dataDir, string $tableName, string $column, array $rows): bool
    {
        if (empty($tableName) || empty($column)) {
            return false;
        }

        $indexList = [];
        foreach ($rows as $row) {
            if (isset($row[$column])) {
                $val = (string)$row[$column];
                $id = $row['id'] ?? ($row['@id'] ?? null);
                if ($id !== null) {
                    $indexList[] = ['key' => $val, 'id' => $id];
                }
            }
        }

        // Sort by key to enable binary search
        usort($indexList, function($a, $b) {
            return strcmp($a['key'], $b['key']);
        });

        $dir = $dataDir . '/_binary_indexes/' . $tableName;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $filePath = $dir . '/' . $column . '.bin.json';
        $encoded = json_encode($indexList, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($filePath, $encoded, LOCK_EX);

        self::$indexCache["{$tableName}.{$column}"] = $indexList;
        return true;
    }

    /**
     * Searches the binary index using O(log N) binary search algorithm.
     *
     * @param string $dataDir Base data directory
     * @param string $tableName Table name
     * @param string $column Column name
     * @param mixed $value Value to search for
     * @return array List of matching row IDs
     */
    public static function searchIndex(string $dataDir, string $tableName, string $column, $value): array
    {
        $cacheKey = "{$tableName}.{$column}";
        if (!isset(self::$indexCache[$cacheKey])) {
            $filePath = $dataDir . '/_binary_indexes/' . $tableName . '/' . $column . '.bin.json';
            if (!file_exists($filePath)) {
                return [];
            }
            $loaded = json_decode(file_get_contents($filePath), true);
            if (!is_array($loaded)) {
                return [];
            }
            self::$indexCache[$cacheKey] = $loaded;
        }

        $indexList = self::$indexCache[$cacheKey];
        $target = (string)$value;

        // Binary search for the first occurrence
        $low = 0;
        $high = count($indexList) - 1;
        $firstMatch = -1;

        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $cmp = strcmp($indexList[$mid]['key'], $target);

            if ($cmp === 0) {
                $firstMatch = $mid;
                $high = $mid - 1; // Look left for earlier occurrences
            } elseif ($cmp < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        if ($firstMatch === -1) {
            return [];
        }

        // Collect all matching IDs (handling duplicate keys)
        $matchingIds = [];
        $len = count($indexList);
        for ($i = $firstMatch; $i < $len; $i++) {
            if (strcmp($indexList[$i]['key'], $target) === 0) {
                $matchingIds[] = $indexList[$i]['id'];
            } else {
                break;
            }
        }

        return array_unique($matchingIds);
    }

    /**
     * Invalidate cached binary index.
     */
    public static function invalidateIndex(string $dataDir, string $tableName, string $column): void
    {
        $cacheKey = "{$tableName}.{$column}";
        unset(self::$indexCache[$cacheKey]);
        $filePath = $dataDir . '/_binary_indexes/' . $tableName . '/' . $column . '.bin.json';
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}
