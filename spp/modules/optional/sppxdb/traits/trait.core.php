<?php

namespace SPPMod\SPPXDB;

use DOMDocument;
use DOMXPath;
use Exception;

trait XDB_Core
{
    public function __construct($db = 'default', $table = null)
    {
        $this->baseDataDir = dirname(__DIR__) . '/data';

        // Load encryption key from environment or settings if possible
        if (class_exists('\\SPP\\SPPConfig')) {
            $this->encryptionKey = \SPP\SPPConfig::get('sys:security.xdb_key', $this->encryptionKey);
        }

        $this->selectDatabase($db);
        if ($table) {
            $this->connect($table);
        }
    }

    /**
     * Selects a database (directory).
     *
     * @param string $db
     * @return $this
     */
    public function selectDatabase($db)
    {
        if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $db)) {
            throw new Exception("Invalid database name");
        }
        $this->dbName = $db;
        $this->dataDir = $this->baseDataDir . '/' . $db;
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
        return $this;
    }

    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }

    public function getDataDir()
    {
        return $this->dataDir;
    }

    public function databaseExists($db)
    {
        return is_dir($this->baseDataDir . '/' . $db);
    }

    public function tableExists($table, $db = null)
    {
        $db = $db ?: $this->dbName;
        return file_exists($this->baseDataDir . '/' . $db . '/' . $table . '.xml');
    }

    /**
     * Creates a new database directory.
     *
     * @param string $dbName
     * @return bool
     */
    public function createDatabase($dbName)
    {
        $path = $this->baseDataDir . '/' . $dbName;
        if (!is_dir($path)) {
            return mkdir($path, 0777, true);
        }
        return true;
    }

    /**
     * Deletes a database directory and all its tables.
     *
     * @param string $dbName
     * @return bool
     */
    public function dropDatabase($dbName)
    {
        $path = $this->baseDataDir . '/' . $dbName;
        if (is_dir($path)) {
            $this->recursiveDelete($path);
            return true;
        }
        return false;
    }

    /**
     * Connects to a specific XML "table" in the current database.
     *
     * @param string $table
     * @return $this
     */
    public function connect($table)
    {
        if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $table)) {
            throw new Exception("Invalid table name");
        }

        if ($this->tableName === $table && $this->doc && $this->inTransaction) {
            return $this;
        }

        $this->tableName = $table;
        $this->filePath = $this->dataDir . '/' . $table . '.xml';

        // Find all segments
        $this->segments = glob($this->dataDir . '/' . $table . '.*.xml');
        if (empty($this->segments) && file_exists($this->filePath)) {
            $this->segments = [$this->filePath];
        }

        // Load ACL
        $this->loadACL();
        $this->checkAccess('read');

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;

        if (file_exists($this->filePath)) {
            $this->lock(LOCK_SH);
            $this->doc->load($this->filePath);
            $this->unlock();
        } else {
            $root = $this->doc->createElement('database');
            $this->doc->appendChild($root);

            // Initialize metadata for auto-increment
            $meta = $this->doc->createElement('_meta');
            $nextId = $this->doc->createElement('next_id', '1');
            $meta->appendChild($nextId);
            $root->appendChild($meta);

            $this->xpath = new DOMXPath($this->doc);
            $this->save();
        }

        $this->xpath = new DOMXPath($this->doc);
        return $this;
    }

}
