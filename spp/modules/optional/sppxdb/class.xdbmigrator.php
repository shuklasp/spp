<?php

namespace SPPMod\SPPXDB;

require_once __DIR__ . '/class.sppxdb.php';

/**
 * class SPP_XDB_Migrator
 * 
 * Provides utility methods to migrate SPP_XDB tables seamlessly between
 * the XML Engine and the SQLite Engine.
 */
class SPP_XDB_Migrator
{
    /**
     * Migrates a specific table from the XML engine to the SQLite engine.
     * 
     * @param string $dbName Name of the database
     * @param string $tableName Name of the table
     * @param bool $dropOriginal Whether to drop the original XML table after success
     * @return int Number of rows migrated
     */
    public static function migrateXmlToSqlite($dbName, $tableName, $dropOriginal = false)
    {
        $xmlEngine = new Engines\XMLEngine($dbName, $tableName);
        $sqliteEngine = new Engines\SQLiteEngine($dbName, $tableName);

        // Extract all rows from XML
        $rows = $xmlEngine->queryX("//row");
        
        if (empty($rows)) {
            return 0; // Nothing to migrate
        }

        // Auto-detect columns from the first row to create the SQLite table schema
        $firstRow = $rows[0];
        $schema = [];
        foreach ($firstRow as $col => $val) {
            // Very simple type inference
            if ($col === 'id' || $col === '@id') continue;
            if (is_numeric($val) && strpos($val, '.') !== false) {
                $schema[$col] = 'float';
            } elseif (is_numeric($val)) {
                $schema[$col] = 'int';
            } else {
                $schema[$col] = 'text';
            }
        }

        // We use the facade to actually execute creating the schema on SQLite
        // But SQLiteEngine handles schema automatically on insert, wait... SQLiteEngine needs createTable?
        // Actually SQLiteEngine's autoInitialize() creates the table if it doesn't exist, using TEXT for all columns dynamically during first insert.
        
        $sqliteEngine->beginTransaction();
        $count = 0;
        try {
            foreach ($rows as $row) {
                // Ensure id maps correctly
                if (isset($row['@id']) && !isset($row['id'])) {
                    $row['id'] = $row['@id'];
                }
                // Strip out metadata attributes
                $cleanRow = [];
                foreach ($row as $k => $v) {
                    if ($k[0] !== '@' || $k === '@id') {
                        $key = ltrim($k, '@');
                        $cleanRow[$key] = $v;
                    }
                }

                $sqliteEngine->insert($cleanRow);
                $count++;
            }
            $sqliteEngine->commit();

            if ($dropOriginal) {
                $xmlEngine->dropTable($tableName);
            }

            return $count;
        } catch (\Exception $e) {
            $sqliteEngine->rollback();
            throw new \Exception("Migration failed: " . $e->getMessage());
        }
    }

    /**
     * Migrates a specific table from the SQLite engine to the XML engine.
     * 
     * @param string $dbName Name of the database
     * @param string $tableName Name of the table
     * @param bool $dropOriginal Whether to drop the original SQLite table after success
     * @return int Number of rows migrated
     */
    public static function migrateSqliteToXml($dbName, $tableName, $dropOriginal = false)
    {
        $sqliteEngine = new Engines\SQLiteEngine($dbName, $tableName);
        $xmlEngine = new Engines\XMLEngine($dbName, $tableName);

        // Ensure XML table is clean
        if (file_exists($xmlEngine->dataDir . '/' . $tableName . '.xml')) {
            $xmlEngine->dropTable($tableName);
        }
        $xmlEngine->createTable($tableName);

        // Fetch all rows from SQLite using raw SQL
        $rows = $sqliteEngine->querySQL("SELECT * FROM " . $sqliteEngine->getTableName());
        
        if (empty($rows)) {
            return 0; // Nothing to migrate
        }

        $xmlEngine->beginTransaction();
        $count = 0;
        try {
            foreach ($rows as $row) {
                $xmlEngine->insert($row);
                $count++;
            }
            $xmlEngine->commit();

            if ($dropOriginal) {
                // To drop an SQLite table, we use raw SQL on the PDO instance
                // But we don't have a direct drop method.
                $sqliteEngine->querySQL("DROP TABLE " . $sqliteEngine->getTableName());
            }

            return $count;
        } catch (\Exception $e) {
            $xmlEngine->rollback();
            throw new \Exception("Migration failed: " . $e->getMessage());
        }
    }
}
