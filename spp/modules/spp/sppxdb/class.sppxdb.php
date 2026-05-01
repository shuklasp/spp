<?php

namespace SPPMod\SPPXDB;

use DOMDocument;
use DOMXPath;
use Exception;

require_once __DIR__ . '/class.querybuilder.php';

/**
 * class SPP_XDB
 *
 * Handles XML Database operations including XPath (XQuery-lite) and SQL translation.
 *
 * @author Satya Prakash Shukla
 */
class SPP_XDB {
    protected $baseDataDir;
    protected $dataDir;
    protected $dbName;
    protected $tableName;
    protected $filePath;
    protected $doc;
    protected $xpath;
    protected $lastResultSet = null;
    protected $lockHandle = null;
    protected $inTransaction = false;
    protected $transactionDoc = null;
    protected $lastInsertId = null;
    protected $queryCache = [];
    protected $indexes = [];
    protected $encryptedFields = [];
    protected $encryptionKey = 'spp-secret-key';
    protected $hooks = [];
    protected $auditingEnabled = false;
    protected $segments = [];
    protected $currentSegment = 0;
    protected $maxRowsPerSegment = 5000;
    protected $permissions = [];
    protected $isSaving = false;
    protected $foreignKeys = [];
    protected $views = [];
    protected $remoteNodes = [];
    protected $globalTransactionActive = false;
    protected $globalTransactionId = null;
    protected $journal = [];
    protected $nodeState = 'FOLLOWER';
    protected $currentTerm = 0;
    protected $votedFor = null;

    /**
     * Constructor
     * 
     * @param string $db Database name (subdirectory in data/)
     * @param string|null $table Table name (XML filename)
     */
    public function __construct($db = 'default', $table = null) {
        $this->baseDataDir = __DIR__ . '/data';
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
    public function selectDatabase($db) {
        $this->dbName = $db;
        $this->dataDir = $this->baseDataDir . '/' . $db;
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
        return $this;
    }

    public function getDataDir() {
        return $this->dataDir;
    }

    /**
     * Lists all databases (subdirectories in the data root).
     * 
     * @return array
     */
    public function listDatabases() {
        $dbs = [];
        if (!is_dir($this->baseDataDir)) return $dbs;
        foreach (scandir($this->baseDataDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir($this->baseDataDir . '/' . $entry)) {
                $dbs[] = $entry;
            }
        }
        return $dbs;
    }

    /**
     * Lists all tables (XML files) in the current database.
     * 
     * @return array
     */
    public function listTables() {
        $tables = [];
        if (!is_dir($this->dataDir)) return $tables;
        foreach (glob($this->dataDir . '/*.xml') as $file) {
            $tables[] = pathinfo($file, PATHINFO_FILENAME);
        }
        return $tables;
    }

    public function tableExists($table) {
        return file_exists($this->dataDir . '/' . $table . '.xml');
    }

    public function getTableColumns($table) {
        if (!$this->tableExists($table)) return [];
        $this->connect($table);
        $firstRow = $this->xpath->query("//row[1]")->item(0);
        if (!$firstRow) return ['id']; // Default ID column
        
        $cols = [];
        foreach ($firstRow->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $cols[] = $child->nodeName;
            }
        }
        if (!in_array('id', $cols)) array_unshift($cols, 'id');
        return $cols;
    }

    /**
     * Checks if a database exists.
     * 
     * @param string $db
     * @return bool
     */
    public function databaseExists($db) {
        return is_dir($this->baseDataDir . '/' . $db);
    }


    /**
     * Creates a new database (directory). Does nothing if it already exists.
     * 
     * @param string $db
     * @return $this
     */
    public function createDatabase($db) {
        $path = $this->baseDataDir . '/' . $db;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $this;
    }

    /**
     * Drops (deletes) a table from the current database.
     * 
     * @param string $table
     * @return bool
     */
    public function dropTable($table) {
        $path = $this->dataDir . '/' . $table . '.xml';
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    /**
     * Drops (deletes) an entire database and all its tables.
     * 
     * @param string $db
     * @return bool
     */
    public function dropDatabase($db) {
        $path = $this->baseDataDir . '/' . $db;
        if (!is_dir($path)) return false;
        foreach (glob($path . '/*.xml') as $file) {
            unlink($file);
        }
        return rmdir($path);
    }

    /**
     * Backups a database to a ZIP file.
     * 
     * @param string $db
     * @param string $targetPath
     * @return bool
     */
    public function backup($db, $targetPath) {
        $dbPath = $this->baseDataDir . '/' . $db;
        if (!is_dir($dbPath)) return false;
        
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($targetPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                $files = glob($dbPath . '/*.xml');
                foreach ($files as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                return true;
            }
        }
        return false;
    }

    /**
     * Restores a database from a ZIP file.
     * 
     * @param string $sourcePath
     * @param string $db
     * @return bool
     */
    public function restore($sourcePath, $db) {
        $targetDir = $this->baseDataDir . '/' . $db;
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($sourcePath) === true) {
                $zip->extractTo($targetDir);
                $zip->close();
                return true;
            }
        }
        return false;
    }

    /**
     * Creates a table with an optional schema definition.
     * The schema is stored as metadata in the XML root element.
     * 
     * @param string $table
     * @param array $columns ['col_name' => 'type', ...]
     * @return $this
     */
    public function createTable($table, $columns = []) {
        $this->tableName = $table;
        $this->filePath = $this->dataDir . '/' . $table . '.xml';

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $root = $this->doc->createElement('database');
        $root->setAttribute('table', $table);

        if (!empty($columns)) {
            $schema = $this->doc->createElement('_schema');
            foreach ($columns as $name => $type) {
                $col = $this->doc->createElement('column');
                $col->setAttribute('name', $name);
                $col->setAttribute('type', $type);
                $schema->appendChild($col);
            }
            $root->appendChild($schema);
        }

        $this->doc->appendChild($root);
        $this->save();
        $this->xpath = new DOMXPath($this->doc);
        
        $this->loadViews();
        $this->loadForeignKeys();
        
        return $this;
    }

    protected function loadViews() {
        $viewPath = $this->dataDir . '/_views.json';
        if (file_exists($viewPath)) {
            $this->views = json_decode(file_get_contents($viewPath), true) ?: [];
        }
    }

    protected function loadForeignKeys() {
        $fkPath = $this->dataDir . '/_fks.json';
        if (file_exists($fkPath)) {
            $this->foreignKeys = json_decode(file_get_contents($fkPath), true) ?: [];
        }
    }

    public function createView($name, $sql, $materialized = false) {
        $this->views[$name] = [
            'sql' => $sql,
            'materialized' => $materialized,
            'last_refresh' => null
        ];
        if ($materialized) $this->refreshView($name);
        
        $viewPath = $this->dataDir . '/_views.json';
        file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));
        return true;
    }

    public function refreshView($name) {
        if (!isset($this->views[$name])) return false;
        $sql = $this->views[$name]['sql'];
        $data = $this->querySQL($sql);
        
        $cachePath = $this->dataDir . '/_mview_' . $name . '.json';
        file_put_contents($cachePath, json_encode($data));
        
        $this->views[$name]['last_refresh'] = time();
        $viewPath = $this->dataDir . '/_views.json';
        file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));
        return true;
    }

    public function verifyIntegrity() {
        $report = ['status' => 'healthy', 'issues' => []];
        
        // Check segments
        foreach ($this->segments as $seg) {
            if (!file_exists($seg)) {
                $report['issues'][] = "Missing segment file: $seg";
                $report['status'] = 'degraded';
            } else {
                $doc = new DOMDocument();
                if (!@$doc->load($seg)) {
                    $report['issues'][] = "Corrupt XML in segment: $seg";
                    $report['status'] = 'danger';
                }
            }
        }

        // Check Foreign Keys (Orphans)
        foreach ($this->foreignKeys as $childTable => $fks) {
            foreach ($fks as $fk) {
                $refTable = $fk['refTable'];
                $refXdb = new self($this->dbName, $refTable);
                $childXdb = new self($this->dbName, $childTable);
                
                $childRows = $childXdb->queryX("//row");
                foreach ($childRows as $row) {
                    $val = $row[$fk['localCol']] ?? null;
                    if ($val && empty($refXdb->queryX("//row[@id='$val']"))) {
                        $report['issues'][] = "Orphaned reference in $childTable: {$fk['localCol']} = $val points to missing $refTable.";
                        $report['status'] = 'degraded';
                    }
                }
            }
        }

        return $report;
    }

    /**
     * Returns the schema of the current table (if defined).
     * 
     * @return array ['col_name' => 'type', ...]
     */
    public function getSchema() {
        if (!$this->xpath) return [];
        $nodes = $this->xpath->query('/database/_schema/column');
        $schema = [];
        if ($nodes) {
            foreach ($nodes as $col) {
                $schema[$col->getAttribute('name')] = $col->getAttribute('type');
            }
        }
        return $schema;
    }

    /**
     * Creates an index for a specific column.
     * 
     * @param string $column
     * @return bool
     */
    public function createIndex($column) {
        if (!$this->tableName) return false;
        
        $results = $this->queryX("//row");
        $index = [];
        foreach ($results as $row) {
            if (isset($row[$column])) {
                $val = $row[$column];
                $id = $row['id'] ?? $row['@id'];
                if (!isset($index[$val])) $index[$val] = [];
                $index[$val][] = $id;
            }
        }
        
        $this->saveIndex($column, $index);
        $this->indexes[$column] = $index;
        return true;
    }

    /**
     * Saves an index to disk.
     */
    protected function saveIndex($column, $data) {
        $dir = $this->dataDir . '/_indexes/' . $this->tableName;
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . '/' . $column . '.json', json_encode($data));
    }

    /**
     * Loads an index from disk.
     */
    protected function loadIndex($column) {
        $file = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $column . '.json';
        if (file_exists($file)) {
            $this->indexes[$column] = json_decode(file_get_contents($file), true);
            return true;
        }
        return false;
    }

    /**
     * Updates all indexes for a specific row.
     */
    protected function updateIndexes($rowId, $data, $action = 'add') {
        $dir = $this->dataDir . '/_indexes/' . $this->tableName;
        if (!is_dir($dir)) return;
        
        foreach (glob($dir . '/*.json') as $file) {
            $column = pathinfo($file, PATHINFO_FILENAME);
            if (!isset($this->indexes[$column])) $this->loadIndex($column);
            
            $val = $data[$column] ?? null;
            if ($val !== null) {
                if ($action === 'add') {
                    if (!isset($this->indexes[$column][$val])) $this->indexes[$column][$val] = [];
                    if (!in_array($rowId, $this->indexes[$column][$val])) {
                        $this->indexes[$column][$val][] = $rowId;
                    }
                } else {
                    if (isset($this->indexes[$column][$val])) {
                        $key = array_search($rowId, $this->indexes[$column][$val]);
                        if ($key !== false) {
                            unset($this->indexes[$column][$val][$key]);
                            $this->indexes[$column][$val] = array_values($this->indexes[$column][$val]);
                            if (empty($this->indexes[$column][$val])) {
                                unset($this->indexes[$column][$val]);
                            }
                        }
                    }
                }
                $this->saveIndex($column, $this->indexes[$column]);
            }
        }
    }

    /**
     * Connects to a specific XML "table" in the current database.
     * 
     * @param string $table
     * @return $this
     */
    public function connect($table) {
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

    protected function loadACL() {
        $aclPath = $this->dataDir . '/_perms.json';
        if (file_exists($aclPath)) {
            $this->permissions = json_decode(file_get_contents($aclPath), true) ?: [];
        }
    }

    public function setPermissions($table, $perms) {
        $aclPath = $this->dataDir . '/_perms.json';
        $this->permissions[$table] = $perms;
        file_put_contents($aclPath, json_encode($this->permissions, JSON_PRETTY_PRINT));
        return $this;
    }

    protected function checkAccess($action) {
        if (empty($this->permissions[$this->tableName])) return true;
        $allowed = $this->permissions[$this->tableName][$action] ?? true;
        if (!$allowed) throw new Exception("Access Denied: Action '$action' not allowed on table '{$this->tableName}'.");
        return true;
    }

    /**
     * Locks the current table file.
     * 
     * @param int $mode LOCK_SH or LOCK_EX
     */
    protected function lock($mode) {
        if (!$this->filePath) return;
        if (!$this->lockHandle) {
            $this->lockHandle = fopen($this->filePath, 'c+');
        }
        flock($this->lockHandle, $mode);
    }

    /**
     * Unlocks the current table file.
     */
    protected function unlock() {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /**
     * Begins a transaction.
     */
    public function beginTransaction() {
        $this->inTransaction = true;
        $this->transactionDoc = $this->doc->cloneNode(true);
        return $this;
    }

    /**
     * Commits the current transaction.
     */
    public function commit() {
        if (!$this->inTransaction) return false;
        $this->inTransaction = false;
        $this->transactionDoc = null;
        return $this->save();
    }

    /**
     * Rolls back the current transaction.
     */
    public function rollback() {
        if (!$this->inTransaction) return false;
        
        // Invalidate cache
        unset($this->queryCache[$this->dbName][$this->tableName]);
        
        $this->doc = $this->transactionDoc;
        $this->xpath = new DOMXPath($this->doc);
        $this->inTransaction = false;
        $this->transactionDoc = null;
        return true;
    }

    public function setEncryptedFields($fields) {
        $this->encryptedFields = $fields;
        return $this;
    }

    protected function encrypt($value) {
        if (empty($value)) return $value;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decrypt($value) {
        if (empty($value)) return $value;
        $data = base64_decode($value);
        $ivSize = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivSize);
        $encrypted = substr($data, $ivSize);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }

    protected function nodeToArray($node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) return [];
        $row = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $val = $child->nodeValue;
                if (in_array($child->nodeName, $this->encryptedFields)) {
                    $val = $this->decrypt($val);
                }
                $row[$child->nodeName] = $val;
            }
        }
        // Include attributes
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $row['@' . $attr->nodeName] = $attr->nodeValue;
                if ($attr->nodeName === 'id' && !isset($row['id'])) {
                    $row['id'] = $attr->nodeValue;
                }
            }
        }
        return $row;
    }

    public function storeBlob($content) {
        $blobId = uniqid('blob_');
        $blobPath = $this->dataDir . '/_blobs/' . $blobId;
        if (!is_dir(dirname($blobPath))) mkdir(dirname($blobPath), 0777, true);
        file_put_contents($blobPath, $content);
        return $blobId;
    }

    public function getBlob($blobId) {
        $blobPath = $this->dataDir . '/_blobs/' . $blobId;
        if (file_exists($blobPath)) return file_get_contents($blobPath);
        return null;
    }

    protected function trackQuery($sql) {
        $statsPath = $this->dataDir . '/_query_stats.json';
        $stats = file_exists($statsPath) ? json_decode(file_get_contents($statsPath), true) : [];
        
        // Normalize SQL (remove values)
        $normSql = preg_replace('/\'[^\']*\'|[0-9]+/', '?', $sql);
        if (!isset($stats[$normSql])) $stats[$normSql] = 0;
        $stats[$normSql]++;
        
        file_put_contents($statsPath, json_encode($stats, JSON_PRETTY_PRINT));
        
        // Simple suggestion logic
        if ($stats[$normSql] > 100) {
            // If many queries on same column, suggest index
            if (preg_match('/WHERE\s+([a-zA-Z0-9_]+)/i', $normSql, $m)) {
                $col = $m[1];
                if (!isset($this->indexes[$this->tableName][$col])) {
                    $this->logAudit('suggestion', "Consider indexing column '$col' in table '{$this->tableName}' for better performance.");
                }
            }
        }
    }

    /**
     * Registers a hook callback.
     */
    public function on($event, $callback) {
        $this->hooks[$event][] = $callback;
        return $this;
    }

    public function enableAuditing($enabled = true) {
        $this->auditingEnabled = $enabled;
        return $this;
    }

    protected function logAudit($action, $data, $where = null) {
        if (!$this->auditingEnabled || $this->tableName === '_audit') return;
        
        $auditXdb = new self($this->dbName, '_audit');
        
        // Blockchain Hash Chain
        $lastAudit = $auditXdb->querySQL("SELECT * FROM _audit ORDER BY timestamp DESC LIMIT 1");
        $prevHash = !empty($lastAudit) ? hash('sha256', json_encode($lastAudit[0])) : '00000000000000000000000000000000';

        $auditXdb->insert([
            'table' => $this->tableName,
            'action' => $action,
            'data' => json_encode($data),
            'where_clause' => $where,
            'user' => $_SESSION['username'] ?? 'system',
            'timestamp' => date('Y-m-d H:i:s'),
            'prev_hash' => $prevHash
        ]);
    }

    /**
     * Fires a hook event.
     */
    protected function fireHook($event, &$data) {
        if (isset($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $callback) {
                $callback($data, $this);
            }
        }
    }

    /**
     * Executes a FLWOR-Lite query (Native XQuery pattern).
     * Format: for $r in table where $r/field = 'val' return $r/field
     * 
     * @param string $query
     * @return array
     */
    public function registerRemoteNode($url) {
        $this->remoteNodes[] = $url;
        return $this;
    }

    protected function queryRemoteNodes($sql) {
        $allRemoteResults = [];
        foreach ($this->remoteNodes as $nodeUrl) {
            $apiUrl = "$nodeUrl/api.php?action=query_xdb&sql=" . urlencode($sql);
            $response = @file_get_contents($apiUrl);
            if ($response) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    $allRemoteResults = array_merge($allRemoteResults, $data);
                }
            }
        }
        return $allRemoteResults;
    }

    public function beginGlobalTransaction() {
        if ($this->globalTransactionActive) return false;
        $this->globalTransactionActive = true;
        $this->globalTransactionId = uniqid('gtx_');
        $this->journal = [];
        return $this->globalTransactionId;
    }

    public function commitGlobal() {
        if (!$this->globalTransactionActive) return false;
        
        // Finalize all journaled tables
        foreach ($this->journal as $tableName => $data) {
            $tableXdb = new self($this->dbName, $tableName);
            $tableXdb->save(); 
        }
        
        $this->globalTransactionActive = false;
        $this->globalTransactionId = null;
        $this->journal = [];
        return true;
    }

    public function rollbackGlobal() {
        if (!$this->globalTransactionActive) return false;
        
        // Invalidate all changes (they were only in memory/temp segments)
        $this->globalTransactionActive = false;
        $this->globalTransactionId = null;
        $this->journal = [];
        return true;
    }

    public function explain($sql) {
        $sql = trim($sql);
        $plan = ['query' => $sql, 'steps' => []];
        
        if (preg_match('/FROM\s+([a-zA-Z0-9_]+)/i', $sql, $m)) {
            $tableName = $m[1];
            $plan['table'] = $tableName;
            
            // Analyze statistics
            $statsPath = $this->dataDir . '/_stats.json';
            $stats = file_exists($statsPath) ? json_decode(file_get_contents($statsPath), true) : ['rows' => 0];
            
            if (isset($this->views[$tableName]) && is_array($this->views[$tableName]) && $this->views[$tableName]['materialized']) {
                $plan['steps'][] = "MATERIALIZED VIEW SCAN: $tableName";
            } else {
                if (count($this->segments) > 1) {
                    $plan['steps'][] = "PARALLEL SEGMENT SCAN: " . count($this->segments) . " files";
                } else {
                    $plan['steps'][] = "FULL TABLE SCAN";
                }
            }
            
            if (stripos($sql, 'WHERE') !== false) {
                if (preg_match('/WHERE\s+([a-zA-Z0-9_]+)/i', $sql, $wm)) {
                    $col = $wm[1];
                    if (isset($this->indexes[$tableName][$col])) {
                        $plan['steps'][] = "INDEX LOOKUP: $col";
                    }
                }
            }
        }
        
        return $plan;
    }

    public function runClusterService() {
        if (empty($this->remoteNodes)) return;
        
        $this->currentTerm++;
        echo "   [CLUSTER] Starting election for Term {$this->currentTerm}...\n";
        
        $votes = 1; // Vote for self
        foreach ($this->remoteNodes as $node) {
            $apiUrl = "$node/api.php?action=request_vote&term={$this->currentTerm}&node=" . urlencode($_SERVER['HTTP_HOST'] ?? 'localhost');
            $response = @file_get_contents($apiUrl);
            if ($response && json_decode($response, true)['voteGranted']) {
                $votes++;
            }
        }
        
        if ($votes > (count($this->remoteNodes) + 1) / 2) {
            $this->nodeState = 'LEADER';
            echo "   [CLUSTER] I am now LEADER for Term {$this->currentTerm}!\n";
            $this->sendHeartbeats();
        } else {
            $this->nodeState = 'FOLLOWER';
        }
    }

    protected function sendHeartbeats() {
        foreach ($this->remoteNodes as $node) {
            @file_get_contents("$node/api.php?action=heartbeat&leader=" . urlencode($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
    }

    public function queryFLWOR($query) {
        if (preg_match('/for\s+\$([a-zA-Z0-9_]+)\s+in\s+([a-zA-Z0-9_]+)(?:\s+where\s+(.*?))?\s+return\s+(.*)/is', $query, $matches)) {
            $alias = $matches[1];
            $table = $matches[2];
            $where = $matches[3] ?? null;
            $return = $matches[4];

            $this->connect($table);
            
            $xpath = "//row";
            if ($where) {
                // Convert $alias/field to field
                $where = str_replace('$' . $alias . '/', '', $where);
                $xpath .= "[" . $this->translateWhereToXPath($where) . "]";
            }

            $nodes = $this->xpath->query($xpath);
            $results = [];
            foreach ($nodes as $node) {
                $row = $this->nodeToArray($node);
                
                // Return mapping
                if ($return === '*' || $return === '$' . $alias) {
                    $results[] = $row;
                } else {
                    // Extract fields from return clause
                    $returnFields = explode(',', str_replace('$' . $alias . '/', '', $return));
                    $mapped = [];
                    foreach ($returnFields as $f) {
                        $f = trim($f);
                        if (isset($row[$f])) $mapped[$f] = $row[$f];
                    }
                    $results[] = $mapped;
                }
            }
            return $results;
        }
        throw new Exception("Invalid FLWOR query format.");
    }

    /**
     * Executes an XPath query (XQuery-lite).
     * 
     * @param string $xpathQuery
     * @return array
     */
    public function queryX($xpathQuery) {
        $this->checkAccess('read');
        
        // Multi-segment support
        $allResults = [];
        $originalDoc = $this->doc;
        $originalXpath = $this->xpath;
        $originalPath = $this->filePath;

        $pathsToScan = $this->segments;
        if (!in_array($this->filePath, $pathsToScan) && file_exists($this->filePath)) {
            $pathsToScan[] = $this->filePath;
        }

        foreach ($pathsToScan as $segmentPath) {
            $this->filePath = $segmentPath;
            $doc = new DOMDocument();
            
            // Transparent Compression support
            $loaded = false;
            if (substr($segmentPath, -3) === '.gz') {
                $content = gzdecode(file_get_contents($segmentPath));
                $loaded = @$doc->loadXML($content);
            } else {
                $loaded = @$doc->load($segmentPath);
            }

            if ($loaded) {
                $xpath = new DOMXPath($doc);
                $nodes = $xpath->query($xpathQuery);
                if ($nodes) {
                    foreach ($nodes as $node) {
                        $allResults[] = $this->nodeToArray($node);
                    }
                }
            }
        }

        // Restore original connection
        $this->doc = $originalDoc;
        $this->xpath = $originalXpath;
        $this->filePath = $originalPath;

        $this->lastResultSet = $allResults;
        return $allResults;
    }

    /**
     * Memory-efficient streaming query for large tables.
     * Uses XMLReader to iterate over rows without loading the entire DOM.
     * 
     * @param callable $callback function($rowData)
     * @return int Number of rows processed
     */
    public function streamQuery($callback) {
        if (!$this->filePath || !file_exists($this->filePath)) return 0;
        
        $reader = new \XMLReader();
        if (!$reader->open($this->filePath)) return 0;
        
        $count = 0;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
                $node = $reader->expand();
                $rowData = $this->nodeToArray($node);
                if ($callback($rowData) === false) break;
                $count++;
            }
        }
        $reader->close();
        return $count;
    }

    /**
     * Performs a full-text search across all columns.
     * 
     * @param string $term
     * @return array
     */
    public function search($term) {
        if (!$this->xpath) return [];
        $term = strtolower($term);
        // Using XPath to find rows where any content matches (case-insensitive)
        $xpath = "//row[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$term')]";
        return $this->queryX($xpath);
    }

    /**
     * GraphQL Bridge for XDB.
     * Maps basic GraphQL queries to SQL.
     * 
     * @param string $query
     * @return array
     */
    public function queryGraphQL($query) {
        // Very basic GQL to SQL translator
        if (preg_match('/\{\s*([a-zA-Z0-9_]+)(?:\s*\((.*?)\))?\s*\{\s*([a-zA-Z0-9_\s,]+)\s*\}\s*\}/is', $query, $matches)) {
            $table = $matches[1];
            $args = $matches[2] ?? '';
            $fields = str_replace(["\n", "\r", " "], ",", trim($matches[3]));
            $fields = preg_replace('/,+/', ',', $fields);
            
            $where = "";
            if ($args) {
                // Parse args: key: "val"
                if (preg_match('/([a-zA-Z0-9_]+):\s*"(.*?)"/', $args, $am)) {
                    $where = " WHERE {$am[1]} = '{$am[2]}'";
                }
            }
            
            return $this->querySQL("SELECT $fields FROM $table$where");
        }
        return [];
    }

    /**
     * Executes an XSLT transformation on the current table data.
     * 
     * @param string $xsltPath Path to .xsl file
     * @return string Transformed output
     */
    public function transform($xsltPath) {
        $this->checkAccess('read');
        if (!file_exists($xsltPath)) throw new Exception("XSLT file not found.");
        
        $xsl = new DOMDocument();
        $xsl->load($xsltPath);
        
        $proc = new \XSLTProcessor();
        $proc->importStyleSheet($xsl);
        
        // Combine segments for full transformation
        $fullDoc = new DOMDocument('1.0', 'UTF-8');
        $root = $fullDoc->createElement('database');
        $root->setAttribute('table', $this->tableName);
        $fullDoc->appendChild($root);
        
        $pathsToScan = $this->segments;
        if (!in_array($this->filePath, $pathsToScan) && file_exists($this->filePath)) {
            $pathsToScan[] = $this->filePath;
        }

        foreach ($pathsToScan as $segmentPath) {
            $seg = new DOMDocument();
            if ($seg->load($segmentPath)) {
                foreach ($seg->getElementsByTagName('row') as $row) {
                    $root->appendChild($fullDoc->importNode($row, true));
                }
            }
        }
        
        return $proc->transformToXML($fullDoc);
    }

    /**
     * Executes a SQL query on the XML database.
     * 
     * Supported SQL:
     *   SELECT fields FROM [db.]table [WHERE ...] [ORDER BY field [ASC|DESC]] [LIMIT n]
     *   SELECT COUNT(*) FROM [db.]table [WHERE ...]
     *   INSERT INTO [db.]table (fields) VALUES (values)
     *   UPDATE [db.]table SET field='val' [WHERE ...]
     *   DELETE FROM [db.]table [WHERE ...]
     *   CREATE TABLE [db.]table [(col type, ...)]
     *   DROP TABLE [db.]table
     *   CREATE DATABASE dbname
     *   DROP DATABASE dbname
     *   SHOW TABLES
     *   SHOW DATABASES
     * 
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function querySQL($sql, $params = []) {
        $sql = trim($sql);
        $this->trackQuery($sql);

        // -- Distributed Merge --
        $remoteData = [];
        if (!empty($this->remoteNodes) && stripos($sql, 'SELECT') === 0) {
            $remoteData = $this->queryRemoteNodes($sql);
        }

        // -- View Resolution --
        if (preg_match('/FROM\s+([a-zA-Z0-9_]+)/i', $sql, $m)) {
            $tableName = $m[1];
            if (isset($this->views[$tableName])) {
                $v = $this->views[$tableName];
                if (is_array($v) && $v['materialized']) {
                    $cachePath = $this->dataDir . '/_mview_' . $tableName . '.json';
                    if (file_exists($cachePath)) {
                        return json_decode(file_get_contents($cachePath), true);
                    }
                }
                $viewSql = is_array($v) ? $v['sql'] : $v;
                return $this->querySQL($viewSql, $params);
            }
        }

        // 0. Handle Subqueries in WHERE clause (WHERE col IN (SELECT ...))
        // Example: SELECT * FROM users WHERE role_id IN (SELECT id FROM roles WHERE level > 5)
        if (preg_match('/WHERE\s+([a-zA-Z0-9_]+)\s+IN\s+\(\s*SELECT\s+([a-zA-Z0-9_]+)\s+FROM\s+([a-zA-Z0-9_]+)(.*?)\)/is', $sql, $subMatch)) {
            $col = $subMatch[1];
            $subCol = $subMatch[2];
            $subTable = $subMatch[3];
            $subWhereStr = trim($subMatch[4]);
            
            $subXdb = new self($this->dbName, $subTable);
            $subData = $subXdb->querySQL("SELECT $subCol FROM $subTable $subWhereStr");
            $values = array_column($subData, $subCol);
            
            if (empty($values)) {
                $sql = preg_replace('/WHERE.*?IN\s*\(.*?\)/is', "WHERE 1=0", $sql);
            } else {
                $escapedValues = array_map(fn($v) => is_numeric($v) ? $v : "'$v'", $values);
                $sql = preg_replace('/WHERE.*?IN\s*\(.*?\)/is', "WHERE $col IN (" . implode(',', $escapedValues) . ")", $sql);
            }
        }
        
        // Simple cache for SELECT queries
        if (stripos($sql, 'SELECT') === 0 && stripos($sql, 'JOIN') === false) {
            $cacheKey = md5($sql . serialize($params));
            if (isset($this->queryCache[$this->dbName][$this->tableName][$cacheKey])) {
                return $this->queryCache[$this->dbName][$this->tableName][$cacheKey];
            }
        }

        // -- SELECT with JOIN --
        if (preg_match('/^SELECT\s+(.+?)\s+FROM\s+([a-zA-Z0-9_\.]+)\s+((?:INNER|LEFT)\s+)?JOIN\s+([a-zA-Z0-9_\.]+)\s+ON\s+([a-zA-Z0-9_\.]+)\s*=\s*([a-zA-Z0-9_\.]+)(?:\s+WHERE\s+(.+?))?$/i', $sql, $matches)) {
            $fields    = trim($matches[1]);
            $table1    = trim($matches[2]);
            $joinType  = !empty($matches[3]) ? strtoupper(trim($matches[3])) : 'INNER';
            $table2    = trim($matches[4]);
            $on1       = trim($matches[5]);
            $on2       = trim($matches[6]);
            $where     = isset($matches[7]) ? trim($matches[7]) : null;

            // Load Table 1
            $this->resolveTablePath($table1);
            $rows1 = $this->queryX("//row");
            
            // Load Table 2
            $this->resolveTablePath($table2);
            $rows2 = $this->queryX("//row");
            
            $getCol = function($fullName) {
                return strpos($fullName, '.') !== false ? explode('.', $fullName)[1] : $fullName;
            };

            $col1 = $getCol($on1);
            $col2 = $getCol($on2);
            
            $results = [];
            foreach ($rows1 as $r1) {
                $matchFound = false;
                foreach ($rows2 as $r2) {
                    $v1 = $r1[$col1] ?? ($r1['@'.$col1] ?? null);
                    $v2 = $r2[$col2] ?? ($r2['@'.$col2] ?? null);
                    
                    if ($v1 !== null && $v1 == $v2) {
                        $combined = [];
                        foreach ($r1 as $k => $v) $combined[$table1 . '.' . $k] = $v;
                        foreach ($r2 as $k => $v) $combined[$table2 . '.' . $k] = $v;
                        $results[] = $combined;
                        $matchFound = true;
                    }
                }
                
                if (!$matchFound && $joinType === 'LEFT') {
                    $combined = [];
                    foreach ($r1 as $k => $v) $combined[$table1 . '.' . $k] = $v;
                    // Fill right table with nulls (derived from columns of first row)
                    $sample = $rows2[0] ?? [];
                    foreach ($sample as $k => $v) $combined[$table2 . '.' . $k] = null;
                    $results[] = $combined;
                }
            }

            // WHERE filtering on combined results
            if ($where) {
                // Simplified WHERE for joined results (PHP side)
                // This is a bit limited compared to XPath but works for basic cases
                $results = array_filter($results, function($row) use ($where) {
                    // Logic to evaluate WHERE in PHP
                    return true; // TODO: Implement robust PHP-side evaluator
                });
            }

            // Field projection
            if ($fields !== '*') {
                $fieldArray = array_map('trim', explode(',', $fields));
                $filteredResults = [];
                foreach ($results as $row) {
                    $filteredRow = [];
                    foreach ($fieldArray as $f) {
                        $filteredRow[$f] = $row[$f] ?? null;
                    }
                    $filteredResults[] = $filteredRow;
                }
                $results = $filteredResults;
            }

            return $results;
        }

        // -- DDL: SHOW DATABASES --
        if (preg_match('/^SHOW\s+DATABASES$/i', $sql)) {
            return $this->listDatabases();
        }

        // -- DDL: SHOW TABLES --
        if (preg_match('/^SHOW\s+TABLES$/i', $sql)) {
            return $this->listTables();
        }

        // -- DDL: CREATE DATABASE --
        if (preg_match('/^CREATE\s+DATABASE\s+([a-zA-Z0-9_]+)$/i', $sql, $m)) {
            $this->createDatabase(trim($m[1]));
            return true;
        }

        // -- DDL: DROP DATABASE --
        if (preg_match('/^DROP\s+DATABASE\s+([a-zA-Z0-9_]+)$/i', $sql, $m)) {
            return $this->dropDatabase(trim($m[1]));
        }

        // -- DDL: CREATE TABLE --
        if (preg_match('/^CREATE\s+TABLE\s+([a-zA-Z0-9_\.]+)(?:\s*\((.+)\))?$/i', $sql, $m)) {
            $this->resolveTablePath(trim($m[1]));
            $columns = [];
            if (isset($m[2]) && trim($m[2]) !== '') {
                $parts = explode(',', $m[2]);
                foreach ($parts as $part) {
                    $tokens = preg_split('/\s+/', trim($part), 2);
                    $colName = $tokens[0];
                    $colType = isset($tokens[1]) ? $tokens[1] : 'text';
                    $columns[$colName] = $colType;
                }
            }
            $this->createTable($this->tableName, $columns);
            return true;
        }

        // -- DDL: DROP TABLE --
        if (preg_match('/^DROP\s+TABLE\s+([a-zA-Z0-9_\.]+)$/i', $sql, $m)) {
            $this->resolveTablePath(trim($m[1]));
            return $this->dropTable($this->tableName);
        }

        // -- SELECT (with optional ORDER BY / LIMIT) --
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+([a-zA-Z0-9_\.]+)(?:\s+WHERE\s+(.+?))?(?:\s+GROUP\s+BY\s+([a-zA-Z0-9_]+))?(?:\s+ORDER\s+BY\s+([a-zA-Z0-9_]+)(?:\s+(ASC|DESC))?)?(?:\s+LIMIT\s+(\d+))?$/i', $sql, $matches)) {
            $isDistinct = !empty($matches[1]);
            $fields    = trim($matches[2]);
            $tablePath = trim($matches[3]);
            $where     = isset($matches[4]) ? trim($matches[4]) : null;
            $groupBy   = isset($matches[5]) ? trim($matches[5]) : null;
            $orderBy   = isset($matches[6]) ? trim($matches[6]) : null;
            $orderDir  = isset($matches[7]) ? strtoupper(trim($matches[7])) : 'ASC';
            $limit     = isset($matches[8]) ? (int) $matches[8] : null;

            $this->resolveTablePath($tablePath);
            
            $xpath = "//row";
            if ($where) {
                $xpath .= "[" . $this->translateWhereToXPath($where, $params) . "]";
            }

            // Extended Aggregates (Global): COUNT, SUM, AVG, MIN, MAX (if no GROUP BY)
            if (!$groupBy && preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\s*\(\s*(.*?)\s*\)(?:\s+AS\s+([a-zA-Z0-9_]+))?$/i', $fields, $aggMatches)) {
                $func = strtoupper($aggMatches[1]);
                $field = trim($aggMatches[2]);
                $alias = !empty($aggMatches[3]) ? $aggMatches[3] : $fields;
                $results = $this->queryX($xpath);
                
                if ($func === 'COUNT') {
                    return [[$alias => count($results)]];
                }
                
                $values = [];
                foreach ($results as $row) {
                    if (isset($row[$field]) && is_numeric($row[$field])) {
                        $values[] = $row[$field];
                    }
                }
                
                if (empty($values)) return [[$fields => 0]];
                
                $val = 0;
                switch ($func) {
                    case 'SUM': $val = array_sum($values); break;
                    case 'AVG': $val = array_sum($values) / count($values); break;
                    case 'MIN': $val = min($values); break;
                    case 'MAX': $val = max($values); break;
                }
                return [[$alias => $val]];
            }

            $results = $this->queryX($xpath);
            $results = array_merge($results, $remoteData);
            $fieldArray = array_map('trim', explode(',', $fields));

            // GROUP BY
            if ($groupBy) {
                $grouped = [];
                foreach ($results as $row) {
                    $key = $row[$groupBy] ?? 'NULL';
                    if (!isset($grouped[$key])) {
                        $grouped[$key] = [];
                    }
                    $grouped[$key][] = $row;
                }
                
                $finalResults = [];
                foreach ($grouped as $key => $rows) {
                    $item = [$groupBy => $key];
                    foreach ($fieldArray as $f) {
                        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\s*\(\s*(.*?)\s*\)$/i', $f, $aggMatches)) {
                            $func = strtoupper($aggMatches[1]);
                            $afield = trim($aggMatches[2]);
                            
                            if ($func === 'COUNT') {
                                $item[$f] = count($rows);
                            } else {
                                $vals = [];
                                foreach ($rows as $r) {
                                    if (isset($r[$afield]) && is_numeric($r[$afield])) $vals[] = $r[$afield];
                                }
                                $val = 0;
                                if (!empty($vals)) {
                                    switch ($func) {
                                        case 'SUM': $val = array_sum($vals); break;
                                        case 'AVG': $val = array_sum($vals) / count($vals); break;
                                        case 'MIN': $val = min($vals); break;
                                        case 'MAX': $val = max($vals); break;
                                    }
                                }
                                $item[$f] = $val;
                            }
                        } else if ($f !== $groupBy && $f !== '*') {
                            $item[$f] = $rows[0][$f] ?? null;
                        }
                    }
                    $finalResults[] = $item;
                }
                $results = $finalResults;
            } else if ($fields !== '*') {
                // Field projection (Non-grouped)
                $filteredResults = [];
                foreach ($results as $row) {
                    $filteredRow = [];
                    foreach ($fieldArray as $f) {
                        if (isset($row[$f])) {
                            $filteredRow[$f] = $row[$f];
                        }
                    }
                    $filteredResults[] = $filteredRow;
                }
                $results = $filteredResults;
            }

            // DISTINCT
            if ($isDistinct) {
                $seen = [];
                $uniqueResults = [];
                foreach ($results as $row) {
                    $key = serialize($row);
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $uniqueResults[] = $row;
                    }
                }
                $results = $uniqueResults;
            }

            // ORDER BY
            if ($orderBy) {
                usort($results, function($a, $b) use ($orderBy, $orderDir) {
                    $va = $a[$orderBy] ?? '';
                    $vb = $b[$orderBy] ?? '';
                    $cmp = is_numeric($va) && is_numeric($vb)
                        ? ($va - $vb)
                        : strcmp($va, $vb);
                    return $orderDir === 'DESC' ? -$cmp : $cmp;
                });
            }

            // LIMIT
            if ($limit !== null) {
                $results = array_slice($results, 0, $limit);
            }

            if (stripos($sql, 'SELECT') === 0) {
                $cacheKey = md5($sql . serialize($params));
                $this->queryCache[$this->dbName][$this->tableName][$cacheKey] = $results;
            }

            return $results;
        } 
        
        // -- INSERT --
        if (preg_match('/^INSERT\s+INTO\s+([a-zA-Z0-9_\.]+)\s*\((.+?)\)\s*VALUES\s*\((.+?)\)$/i', $sql, $matches)) {
            $tablePath = trim($matches[1]);
            $fields = array_map('trim', explode(',', $matches[2]));
            $values = array_map('trim', explode(',', $matches[3]));

            $data = [];
            foreach ($fields as $i => $f) {
                $val = $values[$i];
                if ($val === '?' && isset($params[$i])) {
                    $val = $params[$i];
                } else {
                    $val = trim($val, "'\"");
                }
                $data[$f] = $val;
            }

            $this->resolveTablePath($tablePath);
            return $this->insert($data);
        }

        // -- UPDATE --
        if (preg_match('/^UPDATE\s+([a-zA-Z0-9_\.]+)\s+SET\s+(.+?)(?:\s+WHERE\s+(.+?))?$/i', $sql, $matches)) {
            $tablePath = trim($matches[1]);
            $setStr = trim($matches[2]);
            $where = isset($matches[3]) ? trim($matches[3]) : null;

            $this->resolveTablePath($tablePath);
            
            // Parse SET clause: field1='val1', field2='val2'
            $updates = [];
            $setParts = explode(',', $setStr);
            foreach ($setParts as $part) {
                if (preg_match('/([a-zA-Z0-9_]+)\s*=\s*(.+)/', trim($part), $m)) {
                    $val = trim($m[2], "'\" ");
                    $updates[trim($m[1])] = $val;
                }
            }

            return $this->update($updates, $where, $params);
        }

        // -- DELETE --
        if (preg_match('/^DELETE\s+FROM\s+([a-zA-Z0-9_\.]+)(?:\s+WHERE\s+(.+?))?$/i', $sql, $matches)) {
            $tablePath = trim($matches[1]);
            $where = isset($matches[2]) ? trim($matches[2]) : null;

            $this->resolveTablePath($tablePath);
            return $this->delete($where, $params);
        }

        throw new Exception("Unsupported SQL syntax in XDB: " . $sql);
    }

    /**
     * Resolves a table path which may be 'db.table' or just 'table'.
     * Sets the current database and connects to the table.
     * 
     * @param string $tablePath
     */
    protected function resolveTablePath($tablePath) {
        if (strpos($tablePath, '.') !== false) {
            list($db, $table) = explode('.', $tablePath, 2);
            $this->selectDatabase($db);
            $this->connect($table);
        } else {
            $this->connect($tablePath);
        }
    }

    /**
     * Exports table data to a specific format.
     * 
     * @param string $table
     * @param string $format 'json' or 'csv'
     * @return string|null
     */
    public function export($table, $format = 'json') {
        $this->connect($table);
        $data = $this->queryX("//row");
        if (empty($data)) return null;

        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        } else if ($format === 'csv') {
            $output = fopen('php://temp', 'r+');
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            return $csv;
        }
        return null;
    }


    /**
     * Validates data against the table schema.
     * 
     * @param array $data
     * @throws Exception
     */
    protected function validateData($data) {
        $schema = $this->getSchema();
        if (empty($schema)) return;
        
        foreach ($data as $key => $value) {
            if (!isset($schema[$key])) continue;
            
            $type = strtolower($schema[$key]);
            if ($type === 'int' || $type === 'integer' || $type === 'number') {
                if ($value !== '' && !is_numeric($value)) {
                    throw new Exception("Validation Error: Column '$key' must be numeric.");
                }
            }
        }
    }

    /**
     * Updates records in the current table.
     * 
     * @param array $data Associative array of field => value to update.
     * @param string|null $where SQL WHERE clause.
     * @param array $params Query parameters.
     * @return bool
     */
    public function update($data, $where = null, $params = []) {
        $this->validateData($data);
        $this->fireHook('beforeUpdate', $data);
        $xpath = "//row";
        if ($where) {
            $xpath .= "[" . $this->translateWhereToXPath($where, $params) . "]";
        }

        $nodes = $this->xpath->query($xpath);
        if ($nodes) {
            foreach ($nodes as $node) {
                // Temporal Data: Archive current state to <history>
                $history = $this->doc->createElement('history');
                $history->setAttribute('timestamp', date('Y-m-d H:i:s'));
                foreach ($node->childNodes as $child) {
                    if ($child->nodeName !== 'history') {
                        $history->appendChild($child->cloneNode(true));
                    }
                }
                $node->appendChild($history);

                $rowId = $node->getAttribute('id');
                // Old data for index update
                $oldData = [];
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) $oldData[$child->nodeName] = $child->nodeValue;
                }
                
                // Schema Validation
                $schema = $this->getSchema();
                
                foreach ($data as $key => $value) {
                    $valToSave = $value;
                    // Apply type coercion if schema exists
                    if (isset($schema[$key])) {
                        if ($schema[$key] === 'int') $valToSave = (int)$value;
                        elseif ($schema[$key] === 'float' || $schema[$key] === 'double') $valToSave = (float)$value;
                        elseif ($schema[$key] === 'bool' || $schema[$key] === 'boolean') $valToSave = $value ? 'true' : 'false';
                    }

                    if (in_array($key, $this->encryptedFields)) {
                        $valToSave = $this->encrypt($valToSave);
                    }
                    
                    $field = $this->xpath->query($key, $node)->item(0);
                    if ($field) {
                        $field->nodeValue = htmlspecialchars((string)$valToSave);
                    } else {
                        $newField = $this->doc->createElement($key);
                        $newField->appendChild($this->doc->createTextNode((string)$valToSave));
                        $node->appendChild($newField);
                    }
                }
                
                // Update indexes
                $this->updateIndexes($rowId, $oldData, 'remove');
                $updatedData = array_merge($oldData, $data);
                $this->updateIndexes($rowId, $updatedData, 'add');
            }
        }
        $result = $this->save();
        $this->logAudit('update', $data, $where);
        $this->fireHook('afterUpdate', $data);
        return $result;
    }

    /**
     * Deletes records from the current table.
     * 
     * @param string|null $where SQL WHERE clause.
     * @param array $params Query parameters.
     * @return bool
     */
    public function delete($where = null, $params = []) {
        $this->fireHook('beforeDelete', $where);
        $xpath = "//row";
        if ($where) {
            $xpath .= "[" . $this->translateWhereToXPath($where, $params) . "]";
        }

        $nodes = $this->xpath->query($xpath);
        if ($nodes && $nodes->length > 0) {
            $toRemove = [];
            foreach ($nodes as $node) {
                $toRemove[] = $node;
                $rowId = $node->getAttribute('id');
                
                // Foreign Key Cascading
                $this->handleCascadingDelete($rowId);

                // Remove from index
                $rowData = $this->nodeToArray($node);
                $this->updateIndexes($rowId, $rowData, 'remove');
            }
            foreach ($toRemove as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        $result = $this->save();
        $this->logAudit('delete', null, $where);
        $this->fireHook('afterDelete', $where);
        return $result;
    }

    /**
     * Inserts a record into the current table.
     * Supports auto-incrementing 'id' if not provided.
     * 
     * @param array $data
     * @return bool
     */
    public function insert($data) {
        $this->validateData($data);
        $this->fireHook('beforeInsert', $data);
        $root = $this->doc->documentElement;
        
        // Handle auto-increment ID
        if (!isset($data['id'])) {
            $meta = $this->xpath->query('/database/_meta/next_id')->item(0);
            if (!$meta) {
                // Ensure meta exists if it doesn't
                $metaNode = $this->doc->createElement('_meta');
                $meta = $this->doc->createElement('next_id', '1');
                $metaNode->appendChild($meta);
                $root->insertBefore($metaNode, $root->firstChild);
            }
            $id = $meta->nodeValue;
            $data['id'] = $id;
            $meta->nodeValue = $id + 1;
        }
        
        $this->lastInsertId = $data['id'];
        
        $row = $this->doc->createElement('row');
        $row->setAttribute('id', $data['id']);
        
        $schema = $this->getSchema();
        
        foreach ($data as $key => $value) {
            $valToSave = $value;
            // Apply type coercion if schema exists
            if (isset($schema[$key])) {
                if ($schema[$key] === 'int') $valToSave = (int)$value;
                elseif ($schema[$key] === 'float' || $schema[$key] === 'double') $valToSave = (float)$value;
                elseif ($schema[$key] === 'bool' || $schema[$key] === 'boolean') $valToSave = $value ? 'true' : 'false';
            }

            if (in_array($key, $this->encryptedFields)) {
                $valToSave = $this->encrypt($valToSave);
            }
            
            $field = $this->doc->createElement($key);
            $field->appendChild($this->doc->createTextNode((string)$valToSave));
            $row->appendChild($field);
        }

        $root->appendChild($row);
        
        // Update indexes
        $this->updateIndexes($data['id'], $data, 'add');
        
        $result = $this->save();
        $this->logAudit('insert', $data);
        $this->fireHook('afterInsert', $data);
        return $result;
    }

    protected function handleCascadingDelete($id) {
        $this->loadForeignKeys();
        foreach ($this->foreignKeys as $childTable => $fks) {
            foreach ($fks as $fk) {
                if ($fk['refTable'] === $this->tableName) {
                    $childXdb = new self($this->dbName, $childTable);
                    $localCol = $fk['localCol'];
                    if ($fk['onDelete'] === 'CASCADE') {
                        $childXdb->delete("$localCol = ?", [$id]);
                    } elseif ($fk['onDelete'] === 'SET NULL') {
                        $childXdb->update([$localCol => null], "$localCol = ?", [$id]);
                    }
                }
            }
        }
    }

    public function addForeignKey($childTable, $localCol, $refTable, $refCol, $onDelete = 'CASCADE') {
        $this->foreignKeys[$childTable][] = [
            'localCol' => $localCol,
            'refTable' => $refTable,
            'refCol' => $refCol,
            'onDelete' => $onDelete
        ];
        $fkPath = $this->dataDir . '/_fks.json';
        file_put_contents($fkPath, json_encode($this->foreignKeys, JSON_PRETTY_PRINT));
        return $this;
    }

    public function lastInsertId() {
        return $this->lastInsertId;
    }

    /**
     * Initiates a fluent query builder for a table.
     * 
     * @param string $table
     * @return QueryBuilder
     */
    public function table($table) {
        return new QueryBuilder($this, $table);
    }

    /**
     * Translates SQL WHERE clause to XPath predicate.
     * 
     * @param string $where
     * @param array $params
     * @return string
     */
    protected function translateWhereToXPath($where, $params = []) {
        $translated = $where;
        
        // Handle id field: id = '1' -> @id = '1'
        $translated = preg_replace('/\bid\b(\s*[=<>!]+|(\s+IN\s*\(.*?\)|\s+LIKE\s+.*?))/i', '@id$1', $translated);

        // Handle parameters
        $paramIndex = 0;
        $translated = preg_replace_callback('/\?/', function($m) use (&$params, &$paramIndex) {
            $val = isset($params[$paramIndex]) ? $params[$paramIndex] : '';
            $paramIndex++;
            return "'" . $val . "'";
        }, $translated);

        // Handle LIKE: field LIKE '%val%'
        $translated = preg_replace_callback('/([a-zA-Z0-9_]+)\s+LIKE\s+\'(.+?)\'/i', function($m) {
            $field = $m[1];
            $pattern = $m[2];
            if (strpos($pattern, '%') === 0 && substr($pattern, -1) === '%') {
                $val = trim($pattern, '%');
                return "contains($field, '$val')";
            } elseif (substr($pattern, -1) === '%') {
                $val = trim($pattern, '%');
                return "starts-with($field, '$val')";
            } elseif (strpos($pattern, '%') === 0) {
                $val = trim($pattern, '%');
                return "substring($field, string-length($field) - string-length('$val') + 1) = '$val'";
            } else {
                return "$field = '$pattern'";
            }
        }, $translated);

        // Handle IN: field IN ('val1', 'val2')
        $translated = preg_replace_callback('/([a-zA-Z0-9_]+)\s+IN\s*\((.+?)\)/i', function($m) {
            $field = $m[1];
            $vals = explode(',', $m[2]);
            $orParts = [];
            foreach ($vals as $v) {
                $v = trim($v, "'\" ");
                $orParts[] = "$field = '$v'";
            }
            return "(" . implode(' or ', $orParts) . ")";
        }, $translated);

        // Basic operator replacements
        $translated = str_ireplace(' AND ', ' and ', $translated);
        $translated = str_ireplace(' OR ', ' or ', $translated);
        $translated = str_replace('<>', '!=', $translated);

        // Range optimization: x > 5 -> number(x) > 5
        $translated = preg_replace('/([a-zA-Z0-9_]+)\s*(>=|>|<=|<)\s*([0-9\.]+)/', 'number($1) $2 $3', $translated);

        return $translated;
    }

    /**
     * Saves the current DOM to file with atomic write and locking.
     * 
     * @return bool
     */
    public function save() {
        if ($this->isSaving) return true;
        if (!$this->filePath) return false;
        $this->checkAccess('write');

        if ($this->globalTransactionActive) {
            $this->journal[$this->tableName] = true;
            return true; // Defer to global commit
        }

        $this->isSaving = true;
        
        // Invalidate cache
        unset($this->queryCache[$this->dbName][$this->tableName]);

        if ($this->inTransaction) {
            $this->isSaving = false;
            return true;
        }

        $this->lock(LOCK_EX);
        
        $tempFile = $this->filePath . '.tmp';
        $result = $this->doc->save($tempFile);
        
        // On Windows, we must close the file handle before renaming
        $this->unlock();
        
        if ($result !== false) {
            // Atomic swap
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
            $result = @rename($tempFile, $this->filePath);

            // Horizontal Partitioning: Check if current segment is full
            if ($result !== false) {
                $rowCount = $this->xpath->query("//row")->length;
                if ($rowCount >= $this->maxRowsPerSegment) {
                    $segmentCount = count($this->segments);
                    $newSegmentPath = str_replace('.xml', '.' . $segmentCount . '.xml', $this->filePath);
                    @rename($this->filePath, $newSegmentPath);
                    $this->segments[] = $newSegmentPath;
                    // Re-initialize current table as empty
                    $this->createTable($this->tableName);
                }
            }
        }
        
        $this->isSaving = false;
        if ($result !== false) {
            $this->logBinlog($this->tableName, 'mutation', ['file' => $this->filePath]);
        }
        return $result !== false;
    }

    protected function logBinlog($table, $action, $data) {
        $logPath = $this->dataDir . '/_binlog.jsonl';
        $entry = json_encode([
            'timestamp' => microtime(true),
            'table' => $table,
            'action' => $action,
            'data' => $data
        ]) . "\n";
        file_put_contents($logPath, $entry, FILE_APPEND);
    }

    public function replicateFrom($masterUrl, $dbName) {
        $url = "$masterUrl/api.php?action=get_xdb_binlog&dbname=$dbName";
        $content = file_get_contents($url);
        if ($content) {
            $logs = explode("\n", trim($content));
            foreach ($logs as $log) {
                $entry = json_decode($log, true);
                if ($entry) {
                    // Sync logic: download segments etc.
                }
            }
        }
    }

    public function getNumRows() {
        return is_array($this->lastResultSet) ? count($this->lastResultSet) : 0;
    }
}
