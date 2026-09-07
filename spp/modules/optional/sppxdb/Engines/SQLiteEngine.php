<?php

namespace SPPMod\SPPXDB\Engines;

use PDO;
use Exception;

require_once dirname(__DIR__) . '/traits/trait.acl.php';
require_once dirname(__DIR__) . '/traits/trait.encryption.php';
require_once dirname(__DIR__) . '/traits/trait.raft.php';
require_once dirname(__DIR__) . '/traits/trait.validator.php';

class SQLiteEngine
{
    protected $baseDataDir;
    protected $dataDir;
    protected $dbName;
    protected $tableName;
    protected $pdo;
    protected $lastInsertId = null;
    protected $encryptedFields = [];
    protected $encryptionKey = 'spp-secret-key';
    protected $permissions = [];
    protected $remoteNodes = [];

    use \SPPMod\SPPXDB\XDB_Acl;
    use \SPPMod\SPPXDB\XDB_Encryption;
    use \SPPMod\SPPXDB\XDB_Raft;
    use \SPPMod\SPPXDB\XDB_Validator;
    use \SPPMod\SPPXDB\XDB_Observer;

    public function __construct($db = 'default', $table = null)
    {
        $this->baseDataDir = dirname(__DIR__) . '/data';
        if (class_exists('\\SPP\\SPPConfig')) {
            $this->encryptionKey = \SPP\SPPConfig::get('sys:security.xdb_key', $this->encryptionKey);
        }
        $this->selectDatabase($db);
        if ($table) {
            $this->connect($table);
        }
    }

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
        
        $dbPath = $this->dataDir . '/database.sqlite';
        $this->pdo = new PDO("sqlite:" . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Return arrays instead of objects for backward compatibility with XDB
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $this;
    }

    public function connect($table)
    {
        if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $table)) {
            throw new Exception("Invalid table name");
        }
        $this->tableName = $table;
        
        $this->loadACL();
        $this->checkAccess('read');
        return $this;
    }

    public function querySQL($sql, $params = [])
    {
        $this->checkAccess('read');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Decrypt fields if necessary
        if (!empty($this->encryptedFields)) {
            foreach ($results as &$row) {
                foreach ($this->encryptedFields as $field) {
                    if (isset($row[$field])) {
                        $row[$field] = $this->decrypt($row[$field]);
                    }
                }
            }
        }
        return $results;
    }

    public function insert(array $data)
    {
        $this->checkAccess('write');
        $this->validateJsonSchema($data);
        
        if ($this->fireObserverEvent('creating', $data) === false) {
            return false;
        }

        // Encrypt fields
        if (!empty($this->encryptedFields)) {
            foreach ($this->encryptedFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = $this->encrypt($data[$field]);
                }
            }
        }

        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO " . $this->tableName . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        $this->lastInsertId = $this->pdo->lastInsertId();

        $this->fireObserverEvent('created', $data);

        return $this->lastInsertId;
    }

    public function update(array $data, $whereSql = '', $params = [])
    {
        $this->checkAccess('write');
        $this->validateJsonSchema($data);

        if ($this->fireObserverEvent('updating', $data) === false) {
            return false;
        }

        // Encrypt fields
        if (!empty($this->encryptedFields)) {
            foreach ($this->encryptedFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = $this->encrypt($data[$field]);
                }
            }
        }

        $setParts = [];
        $setParams = [];
        foreach ($data as $k => $v) {
            $setParts[] = "$k = ?";
            $setParams[] = $v;
        }

        $sql = "UPDATE " . $this->tableName . " SET " . implode(', ', $setParts);
        if ($whereSql) {
            $sql .= " WHERE " . $whereSql;
        }

        $allParams = array_merge($setParams, $params);
        $stmt = $this->pdo->prepare($sql);
        $res = $stmt->execute($allParams);

        $this->fireObserverEvent('updated', $data);

        return $res;
    }

    public function delete($whereSql = '', $params = [])
    {
        $this->checkAccess('write');
        
        $data = ['where' => $whereSql, 'params' => $params];
        if ($this->fireObserverEvent('deleting', $data) === false) {
            return false;
        }

        $sql = "DELETE FROM " . $this->tableName;
        if ($whereSql) {
            $sql .= " WHERE " . $whereSql;
        }
        $stmt = $this->pdo->prepare($sql);
        $res = $stmt->execute($params);

        $this->fireObserverEvent('deleted', $data);

        return $res;
    }

    public function createTable($table, $columns = [])
    {
        $this->checkAccess('write');
        // Convert internal XDB column formats to SQLite if provided
        $cols = [];
        foreach ($columns as $c => $type) {
            $cols[] = "$c TEXT"; // Simplify by storing everything as TEXT for loosely typed compat
        }
        if (empty($cols)) {
            $cols[] = "id INTEGER PRIMARY KEY AUTOINCREMENT";
        } else {
            array_unshift($cols, "id INTEGER PRIMARY KEY AUTOINCREMENT");
        }

        $sql = "CREATE TABLE IF NOT EXISTS $table (" . implode(', ', $cols) . ")";
        $this->pdo->exec($sql);
        return true;
    }

    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollBack(); }

    // Dummies for XML trait compatibility
    public function lock($mode) {}
    public function unlock() {}
    public function save() { return true; }

    public function getTableName()
    {
        return $this->tableName;
    }
}
