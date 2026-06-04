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
class SPP_XDB
{
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

    public function __construct($db = 'default', $table = null)
    {
        $this->baseDataDir = __DIR__ . '/data';

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

    public function dropIndex($column)
    {
        if (!$this->tableName) {
            return false;
        }
        $file = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $column . '.json';
        if (file_exists($file)) {
            @unlink($file);
            unset($this->indexes[$column]);
            return true;
        }
        return false;
    }

    public function dropView($name)
    {
        if (isset($this->views[$name])) {
            unset($this->views[$name]);
            $viewPath = $this->dataDir . '/_views.json';
            file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));

            $cachePath = $this->dataDir . '/_mview_' . $name . '.json';
            if (file_exists($cachePath)) {
                @unlink($cachePath);
            }
            return true;
        }
        return false;
    }

    public function truncateTable($tableName)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $nodes = $this->xpath->query("//row");
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
            $dir = $this->dataDir . '/_indexes/' . $this->tableName;
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.json') as $file) {
                    file_put_contents($file, json_encode([]));
                }
            }
            $this->indexes = [];
            return $this->save();
        }
        return false;
    }

    public function renameTable($oldName, $newName)
    {
        $this->resolveTablePath($oldName);
        $oldPath = $this->dataDir . '/' . $this->tableName . '.xml';

        $newDb = $this->dbName;
        $newTable = $newName;
        if (strpos($newName, '.') !== false) {
            list($newDb, $newTable) = explode('.', $newName, 2);
        }
        $newPath = $this->baseDataDir . '/' . $newDb . '/' . $newTable . '.xml';

        if (file_exists($oldPath)) {
            // Rename XML
            @rename($oldPath, $newPath);
            // Rename segments
            foreach (glob($this->dataDir . '/' . $this->tableName . '.*.xml') as $seg) {
                $newSeg = str_replace($this->tableName . '.', $newTable . '.', basename($seg));
                @rename($seg, $this->baseDataDir . '/' . $newDb . '/' . $newSeg);
            }
            // Rename indexes
            $oldIdxDir = $this->dataDir . '/_indexes/' . $this->tableName;
            $newIdxDir = $this->baseDataDir . '/' . $newDb . '/_indexes/' . $newTable;
            if (is_dir($oldIdxDir)) {
                if (!is_dir(dirname($newIdxDir))) {
                    mkdir(dirname($newIdxDir), 0777, true);
                }
                @rename($oldIdxDir, $newIdxDir);
            }
            return true;
        }
        return false;
    }

    public function dropColumn($tableName, $colName)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $schemaNode = $this->xpath->query('/database/_schema')->item(0);
            if ($schemaNode) {
                $colNode = $this->xpath->query("column[@name='{$colName}']", $schemaNode)->item(0);
                if ($colNode) {
                    $schemaNode->removeChild($colNode);

                    $rows = $this->xpath->query("//row/{$colName}");
                    foreach ($rows as $row) {
                        $row->parentNode->removeChild($row);
                    }

                    $this->dropIndex($colName);
                    return $this->save();
                }
            }
        }
        return false;
    }

    public function renameColumn($tableName, $oldCol, $newCol)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $schemaNode = $this->xpath->query('/database/_schema')->item(0);
            if ($schemaNode) {
                $colNode = $this->xpath->query("column[@name='{$oldCol}']", $schemaNode)->item(0);
                if ($colNode) {
                    $colNode->setAttribute('name', $newCol);

                    $rows = $this->xpath->query("//row/{$oldCol}");
                    foreach ($rows as $row) {
                        $newEl = $this->doc->createElement($newCol);
                        while ($row->firstChild) {
                            $newEl->appendChild($row->firstChild);
                        }
                        $row->parentNode->replaceChild($newEl, $row);
                    }

                    $oldIdx = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $oldCol . '.json';
                    $newIdx = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $newCol . '.json';
                    if (file_exists($oldIdx)) {
                        @rename($oldIdx, $newIdx);
                    }
                    return $this->save();
                }
            }
        }
        return false;
    }

    public function modifyColumn($tableName, $colName, $colProps)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $schemaNode = $this->xpath->query('/database/_schema')->item(0);
            if ($schemaNode) {
                $colNode = $this->xpath->query("column[@name='{$colName}']", $schemaNode)->item(0);
                if ($colNode) {
                    $props = ['type' => 'text'];
                    if (preg_match('/^([a-z]+)/i', $colProps, $pm)) {
                        $props['type'] = $pm[1];
                    }
                    if (stripos($colProps, 'NOT NULL') !== false) {
                        $props['notNull'] = true;
                    }
                    if (stripos($colProps, 'PRIMARY KEY') !== false) {
                        $props['primary'] = true;
                    }
                    if (stripos($colProps, 'AUTO_INCREMENT') !== false) {
                        $props['autoIncrement'] = true;
                    }
                    if (stripos($colProps, 'UNIQUE') !== false) {
                        $props['unique'] = true;
                    }

                    foreach (['type', 'notNull', 'primary', 'autoIncrement', 'unique'] as $attr) {
                        $colNode->removeAttribute($attr);
                    }

                    foreach ($props as $k => $v) {
                        $colNode->setAttribute($k, $v === true ? 'true' : $v);
                    }
                    return $this->save();
                }
            }
        }
        return false;
    }


    /**
     * Backups a database to a ZIP file.
     *
     * @param string $db
     * @param string $targetPath
     * @return bool
     */
    public function backup($db, $targetPath)
    {
        $dbPath = $this->baseDataDir . '/' . $db;
        if (!is_dir($dbPath)) {
            return false;
        }

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
    public function restore($sourcePath, $db)
    {
        $targetDir = $this->baseDataDir . '/' . $db;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

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
    public function createTable_OLD($table, $columns = [])
    {
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

    protected function loadViews()
    {
        $viewPath = $this->dataDir . '/_views.json';
        if (file_exists($viewPath)) {
            $this->views = json_decode(file_get_contents($viewPath), true) ?: [];
        }
    }

    protected function loadForeignKeys()
    {
        $fkPath = $this->dataDir . '/_fks.json';
        if (file_exists($fkPath)) {
            $this->foreignKeys = json_decode(file_get_contents($fkPath), true) ?: [];
        }
    }

    public function createView($name, $sql, $materialized = false)
    {
        $this->views[$name] = [
            'sql' => $sql,
            'materialized' => $materialized,
            'last_refresh' => null
        ];
        if ($materialized) {
            $this->refreshView($name);
        }

        $viewPath = $this->dataDir . '/_views.json';
        file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));
        return true;
    }

    public function refreshView($name)
    {
        if (!isset($this->views[$name])) {
            return false;
        }
        $sql = $this->views[$name]['sql'];
        $data = $this->querySQL($sql);

        $cachePath = $this->dataDir . '/_mview_' . $name . '.json';
        file_put_contents($cachePath, json_encode($data));

        $this->views[$name]['last_refresh'] = time();
        $viewPath = $this->dataDir . '/_views.json';
        file_put_contents($viewPath, json_encode($this->views, JSON_PRETTY_PRINT));
        return true;
    }

    public function verifyIntegrity()
    {
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
    public function getSchema()
    {
        if (!$this->xpath) {
            return ['columns' => [], 'constraints' => []];
        }

        $colNodes = $this->xpath->query('/database/_schema/column');
        $conNodes = $this->xpath->query('/database/_schema/constraint');

        $schema = ['columns' => [], 'constraints' => []];

        if ($colNodes) {
            foreach ($colNodes as $col) {
                $name = $col->getAttribute('name');
                $schema['columns'][$name] = [
                    'type' => $col->getAttribute('type'),
                    'notNull' => $col->getAttribute('notNull') === 'true',
                    'unique' => $col->getAttribute('unique') === 'true',
                    'primary' => $col->getAttribute('primary') === 'true',
                    'check' => $col->hasAttribute('check') ? $col->getAttribute('check') : null,
                    'default' => $col->hasAttribute('default') ? $col->getAttribute('default') : null
                ];
            }
        }

        if ($conNodes) {
            foreach ($conNodes as $con) {
                $schema['constraints'][] = [
                    'type' => $con->getAttribute('type'),
                    'columns' => explode(',', $con->getAttribute('columns'))
                ];
            }
        }

        return $schema;
    }

    /**
     * Returns the column names for a specific table.
     *
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName)
    {
        $this->connect($tableName);
        $schema = $this->getSchema();
        if (!empty($schema['columns'])) {
            return array_keys($schema['columns']);
        }

        // Fallback: get keys from first row
        $nodes = $this->xpath->query("//row");
        if ($nodes && $nodes->length > 0) {
            $row = $this->nodeToArray($nodes->item(0));
            // Filter out internal fields like @id, history, etc.
            return array_filter(array_keys($row), function ($k) {
                return $k[0] !== '@' && $k !== 'history';
            });
        }
        return [];
    }

    /**
     * Lists all available databases in the XDB data directory.
     *
     * @return array
     */
    public function listDatabases()
    {
        $path = $this->baseDataDir;
        if (!is_dir($path)) {
            return [];
        }
        $dirs = array_filter(glob($path . '/*'), 'is_dir');
        $dbs = array_map('basename', $dirs);
        // Exclude system/internal dirs if any
        return array_values(array_filter($dbs, function ($d) {
            return $d[0] !== '_';
        }));
    }

    /**
     * Lists all tables in a specific database.
     *
     * @param string|null $dbName
     * @return array
     */
    public function listTables($dbName = null)
    {
        $dbName = $dbName ?: $this->dbName;
        $path = $this->baseDataDir . '/' . $dbName;
        if (!is_dir($path)) {
            return [];
        }
        $files = glob($path . '/*.xml');
        $tables = [];
        foreach ($files as $file) {
            $base = basename($file, '.xml');
            // Ignore segments like table.0.xml
            if (preg_match('/\.\d+$/', $base)) {
                continue;
            }
            // Ignore temporary files
            if (str_ends_with($file, '.tmp')) {
                continue;
            }

            $tables[] = $base;
        }
        return array_unique($tables);
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

    protected function recursiveDelete($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                $this->recursiveDelete($filePath);
            } else {
                @unlink($filePath);
            }
        }
        @rmdir($dir);
    }

    /**
     * Creates a new table (XML file) with an optional schema.
     *
     * @param string $tableName
     * @param array $columns ['name' => 'type', ...]
     * @return bool
     */
    public function createTable($tableName, $columns = [])
    {
        $path = $this->dataDir . '/' . $tableName . '.xml';
        if (file_exists($path)) {
            return false;
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<database name=\"{$this->dbName}\" table=\"{$tableName}\">\n";
        $xml .= "  <_schema>\n";
        foreach ($columns as $name => $props) {
            if ($name === '_constraints' && is_array($props)) {
                foreach ($props as $c) {
                    $ctype = $c['type'] ?? 'unique';
                    $ccols = is_array($c['columns']) ? implode(',', $c['columns']) : $c['columns'];
                    $xml .= "    <constraint type=\"{$ctype}\" columns=\"{$ccols}\" />\n";
                }
                continue;
            }

            if (is_string($props)) {
                $xml .= "    <column name=\"{$name}\" type=\"{$props}\" />\n";
            } else {
                $type = $props['type'] ?? 'text';
                $attrs = "name=\"{$name}\" type=\"{$type}\"";
                if (!empty($props['notNull'])) {
                    $attrs .= " notNull=\"true\"";
                }
                if (!empty($props['unique'])) {
                    $attrs .= " unique=\"true\"";
                }
                if (!empty($props['primary'])) {
                    $attrs .= " primary=\"true\"";
                }
                if (!empty($props['check'])) {
                    $attrs .= " check=\"" . htmlspecialchars($props['check']) . "\"";
                }
                if (isset($props['default'])) {
                    $attrs .= " default=\"" . htmlspecialchars($props['default']) . "\"";
                }
                $xml .= "    <column {$attrs} />\n";

                // If it's a foreign key
                if (!empty($props['references'])) {
                    $refParts = explode('.', $props['references']);
                    if (count($refParts) === 2) {
                        $this->addForeignKey($tableName, $name, $refParts[0], $refParts[1]);
                    }
                }
            }
        }
        $xml .= "  </_schema>\n";
        $xml .= "  <data>\n  </data>\n";
        $xml .= "</database>";

        return file_put_contents($path, $xml) !== false;
    }

    /**
     * Deletes a table and its associated segments and indexes.
     *
     * @param string $tableName
     * @return bool
     */
    public function dropTable($tableName)
    {
        $path = $this->dataDir . '/' . $tableName . '.xml';
        if (file_exists($path)) {
            unlink($path);
            // Drop segments
            foreach (glob($this->dataDir . '/' . $tableName . '.*.xml') as $seg) {
                unlink($seg);
            }
            // Drop indexes
            $idxDir = $this->dataDir . '/_indexes/' . $tableName;
            if (is_dir($idxDir)) {
                foreach (glob($idxDir . '/*') as $f) {
                    unlink($f);
                }
                rmdir($idxDir);
            }
            return true;
        }
        return false;
    }

    /**
     * Creates an index for a specific column.
     *
     * @param string $column
     * @return bool
     */
    public function createIndex($column)
    {
        if (!$this->tableName) {
            return false;
        }

        $results = $this->queryX("//row");
        $index = [];
        foreach ($results as $row) {
            if (isset($row[$column])) {
                $val = $row[$column];
                $id = $row['id'] ?? $row['@id'];
                if (!isset($index[$val])) {
                    $index[$val] = [];
                }
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
    protected function saveIndex($column, $data)
    {
        $dir = $this->dataDir . '/_indexes/' . $this->tableName;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/' . $column . '.json', json_encode($data));
    }

    /**
     * Loads an index from disk.
     */
    protected function loadIndex($column)
    {
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
    protected function updateIndexes($rowId, $data, $action = 'add')
    {
        $dir = $this->dataDir . '/_indexes/' . $this->tableName;
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.json') as $file) {
            $column = pathinfo($file, PATHINFO_FILENAME);
            if (!isset($this->indexes[$column])) {
                $this->loadIndex($column);
            }

            $val = $data[$column] ?? null;
            if ($val !== null) {
                if ($action === 'add') {
                    if (!isset($this->indexes[$column][$val])) {
                        $this->indexes[$column][$val] = [];
                    }
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
    public function connect($table)
    {
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

    protected function loadACL()
    {
        $aclPath = $this->dataDir . '/_perms.json';
        if (file_exists($aclPath)) {
            $this->permissions = json_decode(file_get_contents($aclPath), true) ?: [];
        }
    }

    public function setPermissions($table, $perms)
    {
        $aclPath = $this->dataDir . '/_perms.json';
        $this->permissions[$table] = $perms;
        file_put_contents($aclPath, json_encode($this->permissions, JSON_PRETTY_PRINT));
        return $this;
    }

    protected function checkAccess($action)
    {
        if (empty($this->permissions[$this->tableName])) {
            return true;
        }
        $allowed = $this->permissions[$this->tableName][$action] ?? true;
        if (!$allowed) {
            throw new Exception("Access Denied: Action '$action' not allowed on table '{$this->tableName}'.");
        }
        return true;
    }

    /**
     * Locks the current table file.
     *
     * @param int $mode LOCK_SH or LOCK_EX
     */
    protected function lock($mode)
    {
        if (!$this->filePath) {
            return;
        }
        if (!$this->lockHandle) {
            $this->lockHandle = fopen($this->filePath, 'c+');
        }
        flock($this->lockHandle, $mode);
    }

    /**
     * Unlocks the current table file.
     */
    protected function unlock()
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
        }
    }

    /**
     * Begins a transaction.
     */
    public function beginTransaction()
    {
        $this->inTransaction = true;
        $this->transactionDoc = $this->doc->cloneNode(true);
        return $this;
    }

    /**
     * Commits the current transaction.
     */
    public function commit()
    {
        if (!$this->inTransaction) {
            return false;
        }
        $this->inTransaction = false;
        $this->transactionDoc = null;
        return $this->save();
    }

    /**
     * Rolls back the current transaction.
     */
    public function rollback()
    {
        if (!$this->inTransaction) {
            return false;
        }

        // Invalidate cache
        unset($this->queryCache[$this->dbName][$this->tableName]);

        $this->doc = $this->transactionDoc;
        $this->xpath = new DOMXPath($this->doc);
        $this->inTransaction = false;
        $this->transactionDoc = null;
        return true;
    }

    public function setEncryptedFields($fields)
    {
        $this->encryptedFields = $fields;
        return $this;
    }

    protected function encrypt($value)
    {
        if (empty($value)) {
            return $value;
        }
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decrypt($value)
    {
        if (empty($value)) {
            return $value;
        }
        $data = base64_decode($value);
        $ivSize = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivSize);
        $encrypted = substr($data, $ivSize);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $this->encryptionKey, 0, $iv);
    }

    protected function nodeToArray($node)
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return [];
        }
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

    public function storeBlob($content)
    {
        $blobId = uniqid('blob_');
        $blobPath = $this->dataDir . '/_blobs/' . $blobId;
        if (!is_dir(dirname($blobPath))) {
            mkdir(dirname($blobPath), 0777, true);
        }
        file_put_contents($blobPath, $content);
        return $blobId;
    }

    public function getBlob($blobId)
    {
        $blobPath = $this->dataDir . '/_blobs/' . $blobId;
        if (file_exists($blobPath)) {
            return file_get_contents($blobPath);
        }
        return null;
    }

    protected function trackQuery($sql)
    {
        $statsPath = $this->dataDir . '/_query_stats.json';
        $stats = file_exists($statsPath) ? json_decode(file_get_contents($statsPath), true) : [];

        // Normalize SQL (remove values)
        $normSql = preg_replace('/\'[^\']*\'|[0-9]+/', '?', $sql);
        if (!isset($stats[$normSql])) {
            $stats[$normSql] = 0;
        }
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
    public function on($event, $callback)
    {
        $this->hooks[$event][] = $callback;
        return $this;
    }

    public function enableAuditing($enabled = true)
    {
        $this->auditingEnabled = $enabled;
        return $this;
    }

    protected function logAudit($action, $data, $where = null)
    {
        if (!$this->auditingEnabled || $this->tableName === '_audit') {
            return;
        }

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
    protected function fireHook($event, &$data)
    {
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
    public function registerRemoteNode($url)
    {
        $this->remoteNodes[] = $url;
        return $this;
    }

    protected function queryRemoteNodes($sql)
    {
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

    public function beginGlobalTransaction()
    {
        if ($this->globalTransactionActive) {
            return false;
        }
        $this->globalTransactionActive = true;
        $this->globalTransactionId = uniqid('gtx_');
        $this->journal = [];
        return $this->globalTransactionId;
    }

    public function commitGlobal()
    {
        if (!$this->globalTransactionActive) {
            return false;
        }

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

    public function rollbackGlobal()
    {
        if (!$this->globalTransactionActive) {
            return false;
        }

        // Invalidate all changes (they were only in memory/temp segments)
        $this->globalTransactionActive = false;
        $this->globalTransactionId = null;
        $this->journal = [];
        return true;
    }

    public function explain($sql)
    {
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

    public function runClusterService()
    {
        if (empty($this->remoteNodes)) {
            return;
        }

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

    protected function sendHeartbeats()
    {
        foreach ($this->remoteNodes as $node) {
            @file_get_contents("$node/api.php?action=heartbeat&leader=" . urlencode($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
    }

    public function queryFLWOR($query)
    {
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
                        if (isset($row[$f])) {
                            $mapped[$f] = $row[$f];
                        }
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
    public function queryX($xpathQuery)
    {
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
                $old_error = libxml_use_internal_errors(true);
                $nodes = $xpath->query($xpathQuery);
                if ($nodes === false) {
                    $errors = libxml_get_errors();
                    $error_msg = empty($errors) ? "Unknown error" : $errors[0]->message;
                    error_log("Invalid XPath in queryX: " . $xpathQuery . " Error: " . $error_msg);
                    libxml_clear_errors();
                }
                libxml_use_internal_errors($old_error);
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
    public function streamQuery($callback)
    {
        if (!$this->filePath || !file_exists($this->filePath)) {
            return 0;
        }

        $reader = new \XMLReader();
        if (!$reader->open($this->filePath)) {
            return 0;
        }

        $count = 0;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
                $node = $reader->expand();
                $rowData = $this->nodeToArray($node);
                if ($callback($rowData) === false) {
                    break;
                }
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
    public function search($term)
    {
        if (!$this->xpath) {
            return [];
        }
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
    public function queryGraphQL($query)
    {
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
    public function transform($xsltPath)
    {
        $this->checkAccess('read');
        if (!file_exists($xsltPath)) {
            throw new Exception("XSLT file not found.");
        }

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
    public function querySQL($sql, $params = [])
    {
        $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : dirname(dirname(dirname(__DIR__))) . '/var/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        @file_put_contents($logDir . '/query_log.txt', date('[Y-m-d H:i:s] ') . $sql . "\n", FILE_APPEND);
        $sql = trim($sql);
        $sql = rtrim($sql, ';');
        $this->trackQuery($sql);

        // -- SET OPERATIONS (UNION / UNION ALL) --
        if (preg_match('/\s+UNION\s+(ALL\s+)?/i', $sql)) {
            // Smart split of query string by UNION (excluding inside quotes)
            $subQueries = [];
            $inQuote = false;
            $quoteChar = '';
            $current = '';
            $unionTypes = []; // 'UNION' or 'UNION ALL'

            $i = 0;
            $len = strlen($sql);
            while ($i < $len) {
                $char = $sql[$i];
                if (($char === "'" || $char === '"') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    if ($inQuote && $char === $quoteChar) {
                        $inQuote = false;
                    } elseif (!$inQuote) {
                        $inQuote = true;
                        $quoteChar = $char;
                    }
                }

                if (!$inQuote && substr($sql, $i, 5) === 'UNION') {
                    // Check if it's UNION ALL or UNION
                    $isAll = false;
                    $unionLen = 5;
                    $rest = substr($sql, $i + 5);
                    if (preg_match('/^\s+ALL\s+/i', $rest, $am)) {
                        $isAll = true;
                        $unionLen += strlen($am[0]);
                    }

                    $subQueries[] = trim($current);
                    $unionTypes[] = $isAll ? 'UNION ALL' : 'UNION';
                    $current = '';
                    $i += $unionLen;
                    continue;
                }

                $current .= $char;
                $i++;
            }
            $subQueries[] = trim($current);

            // Execute first query
            $results = $this->querySQL($subQueries[0], $params);
            if (!is_array($results)) {
                $results = [];
            }

            // Execute subsequent queries and combine
            for ($k = 1; $k < count($subQueries); $k++) {
                $subRes = $this->querySQL($subQueries[$k], $params);
                if (!is_array($subRes)) {
                    $subRes = [];
                }

                $type = $unionTypes[$k - 1];
                if ($type === 'UNION ALL') {
                    $results = array_merge($results, $subRes);
                } else {
                    // UNION (Unique result set)
                    $combined = array_merge($results, $subRes);
                    $seen = [];
                    $unique = [];
                    foreach ($combined as $row) {
                        $key = serialize($row);
                        if (!isset($seen[$key])) {
                            $seen[$key] = true;
                            $unique[] = $row;
                        }
                    }
                    $results = $unique;
                }
            }
            return $results;
        }

        // -- Derived Table Subqueries in FROM --
        // Pattern: SELECT ... FROM (SELECT ...) [AS] alias [WHERE ...]
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s*\(\s*(SELECT.*?)\s*\)\s*(?:AS\s+)?([a-zA-Z0-9_]+)(?:\s+WHERE\s+(.+?))?(?:\s+GROUP\s+BY\s+([a-zA-Z0-9_]+))?(?:\s+ORDER\s+BY\s+(.+?))?(?:\s+LIMIT\s+(\d+))?(?:\s+OFFSET\s+(\d+))?$/is', $sql, $m)) {
            $isDistinct = !empty($m[1]);
            $fields     = trim($m[2]);
            $innerSql   = trim($m[3]);
            $alias      = trim($m[4]);
            $where      = isset($m[5]) ? trim($m[5]) : null;
            $groupBy    = isset($m[6]) ? trim($m[6]) : null;
            $orderByStr = isset($m[7]) ? trim($m[7]) : null;
            $limit      = isset($m[8]) ? (int)$m[8] : null;
            $offset     = isset($m[9]) ? (int)$m[9] : null;

            // 1. Run the inner query
            $results = $this->querySQL($innerSql, $params);
            if (!is_array($results)) {
                $results = [];
            }

            // 2. Perform WHERE filtering on PHP array using evaluateExpression
            if ($where) {
                $results = array_filter($results, function ($row) use ($where) {
                    return (bool)$this->evaluateExpression($where, $row);
                });
            }

            // 3. Smart field splitting for outer fields
            $fieldArray = [];
            $depth = 0;
            $inQuote = false;
            $quoteChar = '';
            $current = '';
            for ($i = 0; $i < strlen($fields); $i++) {
                $char = $fields[$i];
                if (($char === "'" || $char === '"') && ($i === 0 || $fields[$i - 1] !== '\\')) {
                    if ($inQuote && $char === $quoteChar) {
                        $inQuote = false;
                    } elseif (!$inQuote) {
                        $inQuote = true;
                        $quoteChar = $char;
                    }
                }
                if (!$inQuote) {
                    if ($char === '(') {
                        $depth++;
                    }
                    if ($char === ')') {
                        $depth--;
                    }
                }
                if ($char === ',' && $depth === 0 && !$inQuote) {
                    $fieldArray[] = trim($current);
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
            $fieldArray[] = trim($current);
            $fieldArray = array_filter(array_map('trim', $fieldArray));

            // 4. GROUP BY on PHP array
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
                                    if (isset($r[$afield]) && is_numeric($r[$afield])) {
                                        $vals[] = $r[$afield];
                                    }
                                }
                                $val = 0;
                                if (!empty($vals)) {
                                    switch ($func) {
                                        case 'SUM': $val = array_sum($vals);
                                            break;
                                        case 'AVG': $val = array_sum($vals) / count($vals);
                                            break;
                                        case 'MIN': $val = min($vals);
                                            break;
                                        case 'MAX': $val = max($vals);
                                            break;
                                    }
                                }
                                $item[$f] = $val;
                            }
                        } elseif ($f !== $groupBy && $f !== '*') {
                            $item[$f] = $rows[0][$f] ?? null;
                        }
                    }
                    $finalResults[] = $item;
                }
                $results = $finalResults;
            } elseif ($fields !== '*') {
                // 5. Outer Field projection
                $filteredResults = [];
                foreach ($results as $row) {
                    $filteredRow = [];
                    foreach ($fieldArray as $f) {
                        if (preg_match('/^(.*?)\s+AS\s+([a-zA-Z0-9_]+)$/i', $f, $aliasMatch)) {
                            $expr = trim($aliasMatch[1]);
                            $al = trim($aliasMatch[2]);
                            $filteredRow[$al] = $this->evaluateExpression($expr, $row);
                        } else {
                            $filteredRow[$f] = $this->evaluateExpression($f, $row);
                        }
                    }
                    $filteredResults[] = $filteredRow;
                }
                $results = $filteredResults;
            }

            // 6. DISTINCT
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

            // 7. ORDER BY
            if ($orderByStr) {
                $parts = explode(',', $orderByStr);
                usort($results, function ($a, $b) use ($parts) {
                    foreach ($parts as $part) {
                        $tokens = preg_split('/\s+/', trim($part));
                        $col = trim($tokens[0]);
                        $dir = isset($tokens[1]) ? strtoupper(trim($tokens[1])) : 'ASC';

                        $valA = $a[$col] ?? null;
                        $valB = $b[$col] ?? null;

                        if ($valA != $valB) {
                            if (is_numeric($valA) && is_numeric($valB)) {
                                return $dir === 'ASC' ? ($valA - $valB) : ($valB - $valA);
                            }
                            return $dir === 'ASC' ? strcmp((string)$valA, (string)$valB) : strcmp((string)$valB, (string)$valA);
                        }
                    }
                    return 0;
                });
            }

            // 8. LIMIT / OFFSET
            if ($offset !== null || $limit !== null) {
                $offset = $offset ?? 0;
                $results = array_slice($results, $offset, $limit);
            }

            return $results;
        }

        // -- SELECT with successive JOIN chains --
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+([a-zA-Z0-9_\.]+)(?:\s+([a-zA-Z0-9_]+))?\s+((?:(?:INNER|LEFT)\s+)?JOIN\s+.*)$/is', $sql, $m)) {
            $isDistinct = !empty($m[1]);
            $fields     = trim($m[2]);
            $baseTable  = trim($m[3]);
            $baseAlias  = !empty($m[4]) ? trim($m[4]) : $baseTable;
            $joinsStr   = trim($m[5]);

            // Extract optional WHERE, ORDER BY, LIMIT, OFFSET from the end of the joins string
            $where = null;
            $orderByStr = null;
            $limit = null;
            $offset = null;

            if (preg_match('/^(.*?)(\s+WHERE\s+(.+?))?(\s+ORDER\s+BY\s+(.+?))?(\s+LIMIT\s+(\d+))?(\s+OFFSET\s+(\d+))?$/is', $joinsStr, $extraMatch)) {
                $joinsStr = trim($extraMatch[1]);
                $where = !empty($extraMatch[3]) ? trim($extraMatch[3]) : null;
                $orderByStr = !empty($extraMatch[5]) ? trim($extraMatch[5]) : null;
                $limit = !empty($extraMatch[7]) ? (int)$extraMatch[7] : null;
                $offset = !empty($extraMatch[9]) ? (int)$extraMatch[9] : null;
            }

            // Parse successive joins
            // Pattern to match each JOIN segment: (INNER|LEFT) JOIN table [alias] ON on1 = on2
            preg_match_all('/(?:(INNER|LEFT)\s+)?JOIN\s+([a-zA-Z0-9_\.]+)(?:\s+([a-zA-Z0-9_]+))?\s+ON\s+([a-zA-Z0-9_\.\`]+)\s*=\s*([a-zA-Z0-9_\.\`]+)/i', $joinsStr, $joinMatches, PREG_SET_ORDER);

            if (!empty($joinMatches)) {
                // 1. Load base table rows
                $this->resolveTablePath($baseTable);
                $results = $this->queryX("//row");

                // Prefix columns of the base table with "baseTable." and "baseAlias."
                $prefixedResults = [];
                foreach ($results as $row) {
                    $prefRow = [];
                    foreach ($row as $k => $v) {
                        $prefRow[$baseTable . '.' . $k] = $v;
                        $prefRow[$baseAlias . '.' . $k] = $v;
                        if (!isset($prefRow[$k])) {
                            $prefRow[$k] = $v;
                        }
                    }
                    $prefixedResults[] = $prefRow;
                }
                $results = $prefixedResults;

                // 2. Process joins sequentially
                foreach ($joinMatches as $jm) {
                    $joinType = !empty($jm[1]) ? strtoupper(trim($jm[1])) : 'INNER';
                    $joinTable = trim($jm[2]);
                    $joinAlias = !empty($jm[3]) ? trim($jm[3]) : $joinTable;
                    $on1 = trim($jm[4], '` ');
                    $on2 = trim($jm[5], '` ');

                    // Load join table rows
                    $subXdb = new self($this->dbName, $joinTable);
                    $joinRows = $subXdb->querySQL("SELECT * FROM $joinTable");
                    if (!is_array($joinRows)) {
                        $joinRows = [];
                    }

                    $nextResults = [];
                    foreach ($results as $r1) {
                        $matchFound = false;
                        foreach ($joinRows as $origR2) {
                            $r2 = [];
                            foreach ($origR2 as $k => $v) {
                                $r2[$joinTable . '.' . $k] = $v;
                                $r2[$joinAlias . '.' . $k] = $v;
                                if (!isset($r2[$k])) {
                                    $r2[$k] = $v;
                                }
                            }

                            $v1 = $r1[$on1] ?? ($r1[strpos($on1, '.') !== false ? explode('.', $on1)[1] : $on1] ?? null);
                            $v2 = $r2[$on2] ?? ($r2[strpos($on2, '.') !== false ? explode('.', $on2)[1] : $on2] ?? null);

                            if ($v1 !== null && $v1 == $v2) {
                                $combined = $r1;
                                foreach ($r2 as $k => $v) {
                                    if (!isset($combined[$k])) {
                                        $combined[$k] = $v;
                                    }
                                }
                                $nextResults[] = $combined;
                                $matchFound = true;
                            }
                        }

                        if (!$matchFound && $joinType === 'LEFT') {
                            $combined = $r1;
                            $sample = $joinRows[0] ?? [];
                            foreach ($sample as $k => $v) {
                                $combined[$joinTable . '.' . $k] = null;
                                $combined[$joinAlias . '.' . $k] = null;
                            }
                            $nextResults[] = $combined;
                        }
                    }
                    $results = $nextResults;
                }

                // 3. Filter using WHERE
                if ($where) {
                    $results = array_filter($results, function ($row) use ($where) {
                        return (bool)$this->evaluateExpression($where, $row);
                    });
                }

                // 4. Project fields
                if ($fields !== '*') {
                    $fieldArray = array_map('trim', explode(',', $fields));
                    $filteredResults = [];
                    foreach ($results as $row) {
                        $filteredRow = [];
                        foreach ($fieldArray as $f) {
                            if (preg_match('/^(.*?)\s+AS\s+([a-zA-Z0-9_]+)$/i', $f, $aliasMatch)) {
                                $expr = trim($aliasMatch[1]);
                                $al = trim($aliasMatch[2]);
                                $filteredRow[$al] = $row[$expr] ?? ($row[$this->evaluateExpression($expr, $row)] ?? null);
                            } else {
                                $bareField = strpos($f, '.') !== false ? explode('.', $f)[1] : $f;
                                $val = $row[$f] ?? ($row[$this->evaluateExpression($f, $row)] ?? null);
                                $filteredRow[$f] = $val;
                                $filteredRow[$bareField] = $val;
                            }
                        }
                        $filteredResults[] = $filteredRow;
                    }
                    $results = $filteredResults;
                }

                // 5. DISTINCT
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

                // 6. LIMIT / OFFSET
                if ($offset !== null || $limit !== null) {
                    $offset = $offset ?? 0;
                    $results = array_slice($results, $offset, $limit);
                }

                return $results;
            }
        }

        $this->trackQuery($sql);

        // -- System Introspection Commands --

        // SHOW DATABASES
        if (preg_match('/^SHOW\s+DATABASES/i', $sql)) {
            $dirs = glob($this->baseDataDir . '/*', GLOB_ONLYDIR);
            $results = [];
            foreach ($dirs as $dir) {
                $results[] = ['Database' => basename($dir)];
            }
            return $results;
        }

        // SHOW TABLES
        if (preg_match('/^SHOW\s+TABLES/i', $sql)) {
            $files = glob($this->dataDir . '/*.xml');
            $results = [];
            foreach ($files as $file) {
                $name = basename($file, '.xml');
                // Skip segment and temporary files
                if (strpos($name, '.') === false && strpos($name, '_mview_') !== 0) {
                    $results[] = ["Tables_in_{$this->dbName}" => $name];
                }
            }
            return $results;
        }

        // DESCRIBE table / DESC table
        if (preg_match('/^(?:DESCRIBE|DESC)\s+([a-zA-Z0-9_\.\`]+)/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            $this->resolveTablePath($tableName);
            if (!file_exists($this->filePath)) {
                throw new Exception("Table '{$this->tableName}' not found in database '{$this->dbName}'");
            }

            $cols = $this->xpath->query("//_schema/column");
            $results = [];
            foreach ($cols as $col) {
                $results[] = [
                    'Field'   => $col->getAttribute('name'),
                    'Type'    => strtoupper($col->getAttribute('type')),
                    'Null'    => $col->getAttribute('notNull') === 'true' ? 'NO' : 'YES',
                    'Key'     => $col->getAttribute('primary') === 'true' ? 'PRI' : ($col->getAttribute('unique') === 'true' ? 'UNI' : ''),
                    'Default' => $col->getAttribute('default'),
                    'Extra'   => $col->getAttribute('check') ? "CHECK (" . $col->getAttribute('check') . ")" : ""
                ];
            }
            return $results;
        }

        // -- Transaction Lifecycle --
        // START TRANSACTION or BEGIN
        if (preg_match('/^(?:START\s+TRANSACTION|BEGIN)/i', $sql)) {
            $this->beginTransaction();
            return true;
        }

        // COMMIT
        if (preg_match('/^COMMIT/i', $sql)) {
            return $this->commit();
        }

        // ROLLBACK
        if (preg_match('/^ROLLBACK/i', $sql)) {
            return $this->rollback();
        }

        // -- EXPLAIN --
        if (preg_match('/^EXPLAIN\s+(.+)$/is', $sql, $m)) {
            return $this->explain($m[1]);
        }

        // -- Database Lifecycle --
        // CREATE DATABASE
        if (preg_match('/^CREATE\s+DATABASE\s+(?:IF\s+NOT\s+EXISTS\s+)?([a-zA-Z0-9_]+)$/i', $sql, $m)) {
            $db = trim($m[1]);
            if (!$this->databaseExists($db)) {
                $this->createDatabase($db);
            }
            return true;
        }

        // DROP DATABASE
        if (preg_match('/^DROP\s+DATABASE\s+(?:IF\s+EXISTS\s+)?([a-zA-Z0-9_]+)$/i', $sql, $m)) {
            $db = trim($m[1]);
            if ($this->databaseExists($db)) {
                return $this->dropDatabase($db);
            }
            return true;
        }

        // -- Table Lifecycle --
        // CREATE TABLE
        if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([a-zA-Z0-9_\.\`]+)(?:\s*\((.+)\))?$/is', $sql, $m)) {
            $tableName = trim($m[1], '` ');

            $dbName = $this->dbName;
            $tableBaseName = $tableName;
            if (strpos($tableName, '.') !== false) {
                list($dbName, $tableBaseName) = explode('.', $tableName, 2);
            }

            if ($this->tableExists($tableBaseName, $dbName)) {
                $this->resolveTablePath($tableName);
                return true; // Already exists
            }

            $this->selectDatabase($dbName);

            $columns = [];
            if (isset($m[2]) && trim($m[2]) !== '') {
                $rawCols = $this->smartSplit($m[2]);
                foreach ($rawCols as $part) {
                    $tokens = preg_split('/\s+/', trim($part), 2);
                    $colName = trim($tokens[0], '` ');
                    $colProps = isset($tokens[1]) ? $tokens[1] : 'text';

                    // Simple property mapping
                    $props = ['type' => 'text'];
                    if (preg_match('/^([a-z]+)/i', $colProps, $pm)) {
                        $props['type'] = $pm[1];
                    }
                    if (stripos($colProps, 'NOT NULL') !== false) {
                        $props['notNull'] = true;
                    }
                    if (stripos($colProps, 'PRIMARY KEY') !== false) {
                        $props['primary'] = true;
                    }
                    if (stripos($colProps, 'AUTO_INCREMENT') !== false) {
                        $props['autoIncrement'] = true;
                    }
                    if (stripos($colProps, 'UNIQUE') !== false) {
                        $props['unique'] = true;
                    }

                    $columns[$colName] = $props;
                }
            }
            $created = $this->createTable($tableBaseName, $columns);
            if ($created) {
                $this->connect($tableBaseName);
            }
            return $created;
        }
        // DROP TABLE
        if (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?([a-zA-Z0-9_\.\`]+)$/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            $this->resolveTablePath($tableName);
            if (file_exists($this->filePath)) {
                return $this->dropTable($this->tableName);
            }
            return true;
        }

        // TRUNCATE TABLE
        if (preg_match('/^TRUNCATE\s+(?:TABLE\s+)?([a-zA-Z0-9_\.\`]+)$/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            return $this->truncateTable($tableName);
        }

        // RENAME TABLE
        if (preg_match('/^RENAME\s+TABLE\s+([a-zA-Z0-9_\.\`]+)\s+TO\s+([a-zA-Z0-9_\.\`]+)$/i', $sql, $m)) {
            $oldName = trim($m[1], '` ');
            $newName = trim($m[2], '` ');
            return $this->renameTable($oldName, $newName);
        }

        // -- Index Management --
        // CREATE INDEX
        if (preg_match('/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?([a-zA-Z0-9_\`]+)?\s*ON\s+([a-zA-Z0-9_\.\`]+)\s*\(\s*([a-zA-Z0-9_\`]+)\s*\)/i', $sql, $m)) {
            $indexName = isset($m[1]) ? trim($m[1], '` ') : null;
            $tablePath = trim($m[2], '` ');
            $column = trim($m[3], '` ');
            $this->resolveTablePath($tablePath);
            $this->createIndex($column);

            if ($indexName) {
                $metaPath = $this->dataDir . '/_indexes/' . $this->tableName . '/_index_meta.json';
                $meta = [];
                if (file_exists($metaPath)) {
                    $meta = json_decode(file_get_contents($metaPath), true) ?: [];
                }
                $meta[$indexName] = $column;
                if (!is_dir(dirname($metaPath))) {
                    mkdir(dirname($metaPath), 0777, true);
                }
                file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT));
            }
            return true;
        }

        // DROP INDEX
        if (preg_match('/^DROP\s+INDEX\s+([a-zA-Z0-9_\`]+)\s+ON\s+([a-zA-Z0-9_\.\`]+)/i', $sql, $m)) {
            $idxName = trim($m[1], '` ');
            $tablePath = trim($m[2], '` ');
            $this->resolveTablePath($tablePath);

            $column = null;
            $metaPath = $this->dataDir . '/_indexes/' . $this->tableName . '/_index_meta.json';
            if (file_exists($metaPath)) {
                $meta = json_decode(file_get_contents($metaPath), true) ?: [];
                if (isset($meta[$idxName])) {
                    $column = $meta[$idxName];
                    unset($meta[$idxName]);
                    file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT));
                }
            }

            if ($column) {
                $file = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $column . '.json';
                if (file_exists($file)) {
                    @unlink($file);
                }
                unset($this->indexes[$column]);
                return true;
            }

            $file = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $idxName . '.json';
            if (file_exists($file)) {
                @unlink($file);
                unset($this->indexes[$idxName]);
                return true;
            }
            return true;
        }

        // -- View Management --
        // CREATE VIEW
        if (preg_match('/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:MATERIALIZED\s+)?VIEW\s+([a-zA-Z0-9_\.\`]+)\s+AS\s+(.+)$/is', $sql, $m)) {
            $viewName = trim($m[1], '` ');
            $viewSql = trim($m[2]);
            $isMaterialized = stripos($sql, 'MATERIALIZED') !== false;

            if (strpos($viewName, '.') !== false) {
                list($db, $viewName) = explode('.', $viewName, 2);
                $this->selectDatabase($db);
            }

            $this->createView($viewName, $viewSql, $isMaterialized);
            return true;
        }

        // DROP VIEW
        if (preg_match('/^DROP\s+VIEW\s+(?:IF\s+EXISTS\s+)?([a-zA-Z0-9_\.\`]+)/i', $sql, $m)) {
            $viewName = trim($m[1], '` ');
            if (strpos($viewName, '.') !== false) {
                list($db, $viewName) = explode('.', $viewName, 2);
                $this->selectDatabase($db);
            }
            return $this->dropView($viewName);
        }

        // -- Alter Actions --
        // ALTER TABLE DROP COLUMN
        if (preg_match('/^ALTER\s+TABLE\s+([a-zA-Z0-9_\.\`]+)\s+DROP\s+(?:COLUMN\s+)?([a-zA-Z0-9_\`]+)$/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            $colName = trim($m[2], '` ');
            return $this->dropColumn($tableName, $colName);
        }

        // ALTER TABLE RENAME COLUMN
        if (preg_match('/^ALTER\s+TABLE\s+([a-zA-Z0-9_\.\`]+)\s+RENAME\s+(?:COLUMN\s+)?([a-zA-Z0-9_\`]+)\s+TO\s+([a-zA-Z0-9_\`]+)$/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            $oldCol = trim($m[2], '` ');
            $newCol = trim($m[3], '` ');
            return $this->renameColumn($tableName, $oldCol, $newCol);
        }

        // ALTER TABLE MODIFY COLUMN
        if (preg_match('/^ALTER\s+TABLE\s+([a-zA-Z0-9_\.\`]+)\s+MODIFY\s+(?:COLUMN\s+)?([a-zA-Z0-9_\`]+)\s+(.+)$/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            $colName = trim($m[2], '` ');
            $colProps = trim($m[3]);
            return $this->modifyColumn($tableName, $colName, $colProps);
        }

        // ALTER TABLE CHANGE COLUMN
        if (preg_match('/^ALTER\s+TABLE\s+([a-zA-Z0-9_\.\`]+)\s+CHANGE\s+(?:COLUMN\s+)?([a-zA-Z0-9_\`]+)\s+([a-zA-Z0-9_\`]+)\s+(.+)$/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            $oldCol = trim($m[2], '` ');
            $newCol = trim($m[3], '` ');
            $colProps = trim($m[4]);
            if ($oldCol !== $newCol) {
                $this->renameColumn($tableName, $oldCol, $newCol);
            }
            return $this->modifyColumn($tableName, $newCol, $colProps);
        }

        // -- Distributed Merge --
        $remoteData = [];
        if (!empty($this->remoteNodes) && stripos($sql, 'SELECT') === 0) {
            $remoteData = $this->queryRemoteNodes($sql);
        }

        // -- View Resolution --
        if (preg_match('/FROM\s+([a-zA-Z0-9_\.\`]+)/i', $sql, $m)) {
            $tableName = trim($m[1], '` ');
            if (strpos($tableName, '.') !== false) {
                list($db, $tableName) = explode('.', $tableName, 2);
            }
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
                $escapedValues = array_map(fn ($v) => is_numeric($v) ? $v : "'$v'", $values);
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

            $getCol = function ($fullName) {
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
                        foreach ($r1 as $k => $v) {
                            $combined[$table1 . '.' . $k] = $v;
                        }
                        foreach ($r2 as $k => $v) {
                            $combined[$table2 . '.' . $k] = $v;
                        }
                        $results[] = $combined;
                        $matchFound = true;
                    }
                }

                if (!$matchFound && $joinType === 'LEFT') {
                    $combined = [];
                    foreach ($r1 as $k => $v) {
                        $combined[$table1 . '.' . $k] = $v;
                    }
                    // Fill right table with nulls (derived from columns of first row)
                    $sample = $rows2[0] ?? [];
                    foreach ($sample as $k => $v) {
                        $combined[$table2 . '.' . $k] = null;
                    }
                    $results[] = $combined;
                }
            }

            // WHERE filtering on combined results
            if ($where) {
                // Simplified WHERE for joined results (PHP side)
                // This is a bit limited compared to XPath but works for basic cases
                $results = array_filter($results, function ($row) use ($where) {
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



        // -- SELECT (with optional ORDER BY / LIMIT) --
        if (preg_match('/^SELECT\s+(DISTINCT\s+)?(.+?)\s+FROM\s+([a-zA-Z0-9_\.]+)(?:\s+WHERE\s+(.+?))?(?:\s+GROUP\s+BY\s+([a-zA-Z0-9_]+))?(?:\s+ORDER\s+BY\s+(.+?))?(?:\s+LIMIT\s+(\d+))?(?:\s+OFFSET\s+(\d+))?$/i', $sql, $matches)) {
            $isDistinct = !empty($matches[1]);
            $fields    = trim($matches[2]);
            $tablePath = trim($matches[3]);
            $where     = isset($matches[4]) ? trim($matches[4]) : null;
            $groupBy   = isset($matches[5]) ? trim($matches[5]) : null;
            $orderByStr = isset($matches[6]) ? trim($matches[6]) : null;
            $limit     = isset($matches[7]) ? (int) $matches[7] : null;
            $offset    = isset($matches[8]) ? (int) $matches[8] : null;

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

                if (empty($values)) {
                    return [[$fields => 0]];
                }

                $val = 0;
                switch ($func) {
                    case 'SUM': $val = array_sum($values);
                        break;
                    case 'AVG': $val = array_sum($values) / count($values);
                        break;
                    case 'MIN': $val = min($values);
                        break;
                    case 'MAX': $val = max($values);
                        break;
                }
                return [[$alias => $val]];
            }

            $results = $this->queryX($xpath);
            $results = array_merge($results, $remoteData);
            $fieldArray = [];
            $depth = 0;
            $inQuote = false;
            $quoteChar = '';
            $current = '';
            for ($i = 0; $i < strlen($fields); $i++) {
                $char = $fields[$i];
                if (($char === "'" || $char === '"') && ($i === 0 || $fields[$i - 1] !== '\\')) {
                    if ($inQuote && $char === $quoteChar) {
                        $inQuote = false;
                    } elseif (!$inQuote) {
                        $inQuote = true;
                        $quoteChar = $char;
                    }
                }
                if (!$inQuote) {
                    if ($char === '(') {
                        $depth++;
                    }
                    if ($char === ')') {
                        $depth--;
                    }
                }
                if ($char === ',' && $depth === 0 && !$inQuote) {
                    $fieldArray[] = trim($current);
                    $current = '';
                } else {
                    $current .= $char;
                }
            }
            $fieldArray[] = trim($current);
            $fieldArray = array_filter(array_map('trim', $fieldArray));

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
                                    if (isset($r[$afield]) && is_numeric($r[$afield])) {
                                        $vals[] = $r[$afield];
                                    }
                                }
                                $val = 0;
                                if (!empty($vals)) {
                                    switch ($func) {
                                        case 'SUM': $val = array_sum($vals);
                                            break;
                                        case 'AVG': $val = array_sum($vals) / count($vals);
                                            break;
                                        case 'MIN': $val = min($vals);
                                            break;
                                        case 'MAX': $val = max($vals);
                                            break;
                                    }
                                }
                                $item[$f] = $val;
                            }
                        } elseif ($f !== $groupBy && $f !== '*') {
                            $item[$f] = $rows[0][$f] ?? null;
                        }
                    }
                    $finalResults[] = $item;
                }
                $results = $finalResults;
            } elseif ($fields !== '*') {
                // Field projection (Non-grouped)
                $filteredResults = [];
                foreach ($results as $row) {
                    $filteredRow = [];
                    foreach ($fieldArray as $f) {
                        if (preg_match('/^(.*?)\s+AS\s+([a-zA-Z0-9_]+)$/i', $f, $aliasMatch)) {
                            $expr = trim($aliasMatch[1]);
                            $alias = trim($aliasMatch[2]);
                            $filteredRow[$alias] = $this->evaluateExpression($expr, $row);
                        } else {
                            $filteredRow[$f] = $this->evaluateExpression($f, $row);
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

            // ORDER BY (Multi-column support)
            $sortCriteria = [];
            if ($orderByStr) {
                $parts = explode(',', $orderByStr);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (preg_match('/^([a-zA-Z0-9_\.]+)(?:\s+(ASC|DESC))?$/i', $part, $pm)) {
                        $sortCriteria[] = [
                            'field' => trim($pm[1]),
                            'dir' => isset($pm[2]) ? strtoupper($pm[2]) : 'ASC'
                        ];
                    }
                }
            }
            if (!empty($sortCriteria)) {
                usort($results, function ($a, $b) use ($sortCriteria) {
                    foreach ($sortCriteria as $criteria) {
                        $field = $criteria['field'];
                        $dir = $criteria['dir'];

                        $va = $a[$field] ?? '';
                        $vb = $b[$field] ?? '';

                        if (is_numeric($va) && is_numeric($vb)) {
                            $cmp = $va - $vb;
                        } else {
                            $cmp = strcmp((string)$va, (string)$vb);
                        }

                        if ($cmp !== 0) {
                            return $dir === 'DESC' ? -$cmp : $cmp;
                        }
                    }
                    return 0;
                });
            }

            // LIMIT and OFFSET pagination
            if ($limit !== null || $offset !== null) {
                $start = $offset !== null ? $offset : 0;
                $len = $limit !== null ? $limit : null;
                $results = array_slice($results, $start, $len);
            }

            if (stripos($sql, 'SELECT') === 0) {
                $cacheKey = md5($sql . serialize($params));
                $this->queryCache[$this->dbName][$this->tableName][$cacheKey] = $results;
            }

            return $results;
        }

        // -- INSERT --
        if (preg_match('/^INSERT\s+INTO\s+([a-zA-Z0-9_\.]+)\s*(?:\((.+?)\))?\s*VALUES\s*(.+)$/is', $sql, $matches)) {
            $tablePath = trim($matches[1]);
            $hasCols = !empty($matches[2]);
            $valuesPart = trim($matches[3]);

            $this->resolveTablePath($tablePath);

            if ($hasCols) {
                $fields = array_map('trim', explode(',', $matches[2]));
            } else {
                $fields = $this->getTableColumns($this->tableName);
            }

            // Smarter comma splitting (handles commas inside quotes)
            $splitCsv = function ($str) {
                return str_getcsv($str, ',', "'");
            };

            // Extract all value blocks: (val1, val2), (val3, val4)
            preg_match_all('/\((.*?)\)/s', $valuesPart, $valueBlocks);

            if (empty($valueBlocks[1])) {
                throw new Exception("Invalid INSERT syntax: no values block found.");
            }

            $lastId = null;
            $this->beginTransaction();
            try {
                foreach ($valueBlocks[1] as $blockIndex => $block) {
                    $values = array_map('trim', $splitCsv($block));
                    $data = [];
                    foreach ($fields as $i => $f) {
                        $val = $values[$i] ?? null;

                        // Positional parameter resolution
                        if ($val === '?') {
                            $paramIdx = ($blockIndex * count($fields)) + $i;
                            if (array_key_exists($paramIdx, $params)) {
                                $val = $params[$paramIdx];
                            }
                        } elseif (is_string($val) && str_starts_with($val, ':') && array_key_exists($val, $params)) {
                            $val = $params[$val];
                        }

                        // Optionally strip quotes if it's a literal string and not a parameter
                        if (is_string($val) && preg_match('/^[\'"](.*)[\'"]$/', $val, $m)) {
                            $val = $m[1];
                        }
                        if ($val !== null && strcasecmp($val, 'NULL') === 0) {
                            $val = null;
                        }
                        $data[$f] = $val;
                    }
                    $this->insert($data);
                    $lastId = $this->lastInsertId;
                }
                $this->commit();
            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }

            $this->lastInsertId = $lastId;
            return true;
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
            $paramIndex = 0;
            foreach ($setParts as $part) {
                if (preg_match('/([a-zA-Z0-9_]+)\s*=\s*(.+)/', trim($part), $m)) {
                    $f = trim($m[1]);
                    $val = trim($m[2], "'\" ");
                    if (strcasecmp($val, 'NULL') === 0) {
                        $val = null;
                    }
                    if ($val === '?') {
                        if (array_key_exists($paramIndex, $params)) {
                            $val = $params[$paramIndex];
                        }
                        $paramIndex++;
                    } elseif (is_string($val) && str_starts_with($val, ':') && array_key_exists($val, $params)) {
                        $val = $params[$val];
                    }
                    $updates[$f] = $val;
                }
            }

            $whereParams = $params;
            if (array_key_exists(0, $params)) {
                $whereParams = array_slice($params, $paramIndex);
            }

            return $this->update($updates, $where, $whereParams);
        }

        // -- DELETE --
        if (preg_match('/^DELETE\s+FROM\s+([a-zA-Z0-9_\.]+)(?:\s+WHERE\s+(.+?))?$/i', $sql, $matches)) {
            $tablePath = trim($matches[1]);
            $where = isset($matches[2]) ? trim($matches[2]) : null;

            $this->resolveTablePath($tablePath);
            return $this->delete($where, $params);
        }

        // -- DDL: ALTER TABLE ADD COLUMN --
        if (preg_match('/^ALTER\s+TABLE\s+([a-zA-Z0-9_\.\`]+)\s+ADD\s+(?:COLUMN\s+)?([a-zA-Z0-9_\`]+)\s+(.+)$/i', $sql, $matches)) {
            $tablePath = trim($matches[1], '` ');
            $colName = trim($matches[2], '` ');
            $colProps = trim($matches[3]);

            $this->resolveTablePath($tablePath);

            if ($this->doc && $this->xpath) {
                $schemaNode = $this->xpath->query('/database/_schema')->item(0);
                if (!$schemaNode) {
                    $schemaNode = $this->doc->createElement('_schema');
                    $root = $this->doc->documentElement;
                    $dataNode = $this->xpath->query('/database/data')->item(0);
                    if ($dataNode) {
                        $root->insertBefore($schemaNode, $dataNode);
                    } else {
                        $rowNode = $this->xpath->query('/database/row')->item(0);
                        if ($rowNode) {
                            $root->insertBefore($schemaNode, $rowNode);
                        } else {
                            $root->appendChild($schemaNode);
                        }
                    }
                    $this->xpath = new DOMXPath($this->doc);
                }

                $colNode = $this->xpath->query("column[@name='{$colName}']", $schemaNode)->item(0);
                if (!$colNode) {
                    $newCol = $this->doc->createElement('column');
                    $newCol->setAttribute('name', $colName);

                    $props = ['type' => 'text'];
                    if (preg_match('/^([a-z]+)/i', $colProps, $pm)) {
                        $props['type'] = $pm[1];
                    }
                    if (stripos($colProps, 'NOT NULL') !== false) {
                        $props['notNull'] = true;
                    }
                    if (stripos($colProps, 'PRIMARY KEY') !== false) {
                        $props['primary'] = true;
                    }
                    if (stripos($colProps, 'AUTO_INCREMENT') !== false) {
                        $props['autoIncrement'] = true;
                    }
                    if (stripos($colProps, 'UNIQUE') !== false) {
                        $props['unique'] = true;
                    }

                    foreach ($props as $k => $v) {
                        $newCol->setAttribute($k, $v === true ? 'true' : $v);
                    }

                    $schemaNode->appendChild($newCol);
                    return $this->save();
                }
            }
            return true;
        }

        throw new Exception("Unsupported SQL syntax in XDB: " . $sql);
    }

    /**
     * Resolves a table path which may be 'db.table' or just 'table'.
     * Sets the current database and connects to the table.
     *
     * @param string $tablePath
     */
    protected function smartSplit($string, $delimiter = ',')
    {
        $parts = [];
        $depth = 0;
        $current = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            if ($char === '(') {
                $depth++;
            }
            if ($char === ')') {
                $depth--;
            }
            if ($char === $delimiter && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $parts[] = $current;
        return array_map('trim', array_filter($parts));
    }

    protected function resolveTablePath($tablePath)
    {
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
    public function export($table, $format = 'json')
    {
        $this->connect($table);
        $data = $this->queryX("//row");
        if (empty($data)) {
            return null;
        }

        if ($format === 'json') {
            return json_encode($data, JSON_PRETTY_PRINT);
        } elseif ($format === 'csv') {
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
    protected function validateData($data, $isInsert = true)
    {
        $schema = $this->getSchema();
        if (empty($schema['columns'])) {
            return;
        }

        foreach ($schema['columns'] as $name => $props) {
            $value = $data[$name] ?? null;

            // 1. Check NOT NULL / Required
            if ($props['notNull'] && $value === null) {
                if ($isInsert && $props['default'] === null) {
                    if (empty($props['primary']) && empty($props['autoIncrement'])) {
                        throw new Exception("Validation Error: Column '$name' cannot be null.");
                    }
                } elseif (!$isInsert && array_key_exists($name, $data)) {
                    throw new Exception("Validation Error: Column '$name' cannot be null.");
                }
            }

            if ($value === null) {
                continue;
            }

            // 2. Check Type
            $type = strtolower($props['type']);
            if ($type === 'int' || $type === 'integer' || $type === 'number') {
                $isExpression = is_string($value) && preg_match('/^([a-zA-Z0-9_]+)\s*([\+\-\*\/])\s*([a-zA-Z0-9_\.\'\"]+)$/', trim($value));
                if (!$isExpression && $value !== '' && !is_numeric($value)) {
                    throw new Exception("Validation Error: Column '$name' must be numeric. Given: " . var_export($value, true));
                }
            }

            // 3. Check SINGLE UNIQUE / PRIMARY
            if ($props['unique'] || $props['primary']) {
                $id = $data['id'] ?? null;
                $xpath = "//row[" . $name . " = '" . addslashes($value) . "'";
                if ($id) {
                    $xpath .= " and @id != '$id'";
                }
                $xpath .= "]";

                $existing = $this->xpath->query($xpath);
                if ($existing && $existing->length > 0) {
                    throw new Exception("Validation Error: Column '$name' value '$value' must be unique.");
                }
            }

            // 4. Check VALUE CONSTRAINT (Check)
            if ($props['check']) {
                $tempDoc = new DOMDocument();
                $tempRow = $tempDoc->createElement('row');
                foreach ($data as $k => $v) {
                    $f = $tempDoc->createElement($k);
                    $f->appendChild($tempDoc->createTextNode((string)$v));
                    $tempRow->appendChild($f);
                }
                $tempDoc->appendChild($tempRow);
                $tempXpath = new DOMXPath($tempDoc);

                // Evaluate the check expression. Expecting it to be a boolean XPath expression
                // e.g. "age > 18" becomes "/row[age > 18]"
                $expression = "/row[" . $props['check'] . "]";
                $res = $tempXpath->query($expression);
                if (!$res || $res->length === 0) {
                    throw new Exception("Validation Error: Column '$name' failed value constraint: " . $props['check']);
                }
            }
        }

        // 5. Check COMPOSITE CONSTRAINTS
        foreach ($schema['constraints'] as $con) {
            if ($con['type'] === 'unique' || $con['type'] === 'primary') {
                $id = $data['id'] ?? null;
                $predicateParts = [];
                $validConstraint = true;
                foreach ($con['columns'] as $col) {
                    if (!isset($data[$col])) {
                        $validConstraint = false;
                        break;
                    }
                    $predicateParts[] = "$col = '" . addslashes($data[$col]) . "'";
                }

                if ($validConstraint) {
                    $xpath = "//row[" . implode(' and ', $predicateParts) . "]";
                    if ($id) {
                        $xpath = "//row[" . implode(' and ', $predicateParts) . " and @id != '$id']";
                    }

                    $existing = $this->xpath->query($xpath);
                    if ($existing && $existing->length > 0) {
                        $colList = implode(', ', $con['columns']);
                        throw new Exception("Validation Error: Composite key ($colList) must be unique.");
                    }
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
    public function update($data, $where = null, $params = [])
    {
        $this->validateData($data, false);
        $this->fireHook('beforeUpdate', $data);

        $triggerData = ['table' => $this->tableName, 'data' => &$data, 'where' => $where, 'params' => $params];
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('xdb.before_update', $triggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.before_update", $triggerData);
        }
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
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $oldData[$child->nodeName] = $child->nodeValue;
                    }
                }

                // Schema Validation
                $schema = $this->getSchema();

                $rowComputed = [];
                foreach ($data as $key => $value) {
                    $valToSave = $value;

                    // Evaluate arithmetic expression
                    if (is_string($value) && preg_match('/^([a-zA-Z0-9_]+)\s*([\+\-\*\/])\s*([a-zA-Z0-9_\.\'\"]+)$/', trim($value), $expMatch)) {
                        $colName = $expMatch[1];
                        $op = $expMatch[2];
                        $operandStr = trim($expMatch[3], "'\" ");

                        $currentVal = 0;
                        $fieldNode = $this->xpath->query($colName, $node)->item(0);
                        if ($fieldNode) {
                            $currentVal = (float)$fieldNode->nodeValue;
                        }

                        if (is_numeric($operandStr)) {
                            $operand = (float)$operandStr;
                        } else {
                            $opNode = $this->xpath->query($operandStr, $node)->item(0);
                            $operand = $opNode ? (float)$opNode->nodeValue : 0;
                        }

                        switch ($op) {
                            case '+': $valToSave = $currentVal + $operand;
                                break;
                            case '-': $valToSave = $currentVal - $operand;
                                break;
                            case '*': $valToSave = $currentVal * $operand;
                                break;
                            case '/': $valToSave = $operand != 0 ? $currentVal / $operand : 0;
                                break;
                        }
                    }

                    // Apply type coercion if schema exists
                    if (isset($schema['columns'][$key])) {
                        $stype = strtolower($schema['columns'][$key]['type']);
                        if ($stype === 'int') {
                            $valToSave = (int)$valToSave;
                        } elseif ($stype === 'float' || $stype === 'double') {
                            $valToSave = (float)$valToSave;
                        } elseif ($stype === 'bool' || $stype === 'boolean') {
                            $valToSave = $valToSave ? 'true' : 'false';
                        }
                    }

                    if (in_array($key, $this->encryptedFields)) {
                        $valToSave = $this->encrypt($valToSave);
                    }

                    $rowComputed[$key] = $valToSave;

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
                $updatedData = array_merge($oldData, $rowComputed);
                $this->updateIndexes($rowId, $updatedData, 'add');
            }
        }
        $result = $this->save();
        $this->logAudit('update', $data, $where);
        $this->fireHook('afterUpdate', $data);

        if (class_exists('\\SPP\\SPPEvent')) {
            $triggerData = ['table' => $this->tableName, 'data' => $data, 'count' => $nodes->length];
            \SPP\SPPEvent::fireEvent('xdb.after_update', $triggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.after_update", $triggerData);
        }

        return $result;
    }

    /**
     * Deletes records from the current table.
     *
     * @param string|null $where SQL WHERE clause.
     * @param array $params Query parameters.
     * @return bool
     */
    public function delete($where = null, $params = [])
    {
        $this->fireHook('beforeDelete', $where);

        $triggerData = ['table' => $this->tableName, 'where' => $where, 'params' => $params];
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('xdb.before_delete', $triggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.before_delete", $triggerData);
        }
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

        if (class_exists('\\SPP\\SPPEvent')) {
            $triggerData = ['table' => $this->tableName, 'where' => $where, 'count' => $nodes->length];
            \SPP\SPPEvent::fireEvent('xdb.after_delete', $triggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.after_delete", $triggerData);
        }

        return $result;
    }

    /**
     * Inserts a record into the current table.
     * Supports auto-incrementing 'id' if not provided.
     *
     * @param array $data
     * @return bool
     */
    public function insert($data)
    {
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

        $this->validateData($data, true);
        $this->fireHook('beforeInsert', $data);

        $triggerData = ['table' => $this->tableName, 'data' => &$data];
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('xdb.before_insert', $triggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.before_insert", $triggerData);
        }

        $this->lastInsertId = $data['id'];

        $row = $this->doc->createElement('row');
        $row->setAttribute('id', $data['id']);

        $schema = $this->getSchema();

        // Ensure all schema fields exist, applying defaults if needed
        foreach ($schema['columns'] as $name => $props) {
            if (!isset($data[$name]) && $props['default'] !== null) {
                $data[$name] = $props['default'];
            }
        }

        foreach ($data as $key => $value) {
            $valToSave = $value;
            // Apply type coercion if schema exists
            if (isset($schema['columns'][$key])) {
                $stype = strtolower($schema['columns'][$key]['type']);
                if ($stype === 'int') {
                    $valToSave = (int)$value;
                } elseif ($stype === 'float' || $stype === 'double') {
                    $valToSave = (float)$value;
                } elseif ($stype === 'bool' || $stype === 'boolean') {
                    $valToSave = $value ? 'true' : 'false';
                }
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

        if (class_exists('\\SPP\\SPPEvent')) {
            $triggerData = ['table' => $this->tableName, 'data' => $data, 'id' => $this->lastInsertId];
            \SPP\SPPEvent::fireEvent('xdb.after_insert', $triggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.after_insert", $triggerData);
        }

        return $result;
    }

    protected function handleCascadingDelete($id)
    {
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

    public function addForeignKey($childTable, $localCol, $refTable, $refCol, $onDelete = 'CASCADE')
    {
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

    public function lastInsertId()
    {
        return $this->lastInsertId;
    }

    /**
     * Initiates a fluent query builder for a table.
     *
     * @param string $table
     * @return QueryBuilder
     */
    public function table($table)
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Translates SQL WHERE clause to XPath predicate.
     *
     * @param string $where
     * @param array $params
     * @return string
     */
    protected function translateWhereToXPath($where, $params = [])
    {
        $translated = $where;

        // Handle id field: id = '1' -> @id = '1'
        $translated = preg_replace('/\bid\b(\s*[=<>!]+|(\s+IN\s*\(.*?\)|\s+LIKE\s+.*?))/i', '@id$1', $translated);

        // Handle positional parameters (?)
        $paramIndex = 0;
        $translated = preg_replace_callback('/\?/', function ($m) use (&$params, &$paramIndex) {
            $val = isset($params[$paramIndex]) ? $params[$paramIndex] : '';
            $paramIndex++;
            return "'" . $val . "'";
        }, $translated);

        // Handle named parameters (:name)
        if (preg_match_all('/(:[a-zA-Z0-9_]+)/', $translated, $namedMatches)) {
            foreach ($namedMatches[1] as $pName) {
                if (array_key_exists($pName, $params)) {
                    $translated = str_replace($pName, "'" . $params[$pName] . "'", $translated);
                }
            }
        }

        // Handle LIKE: field LIKE '%val%'
        $translated = preg_replace_callback('/([a-zA-Z0-9_]+)\s+LIKE\s+\'(.+?)\'/i', function ($m) {
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
        $translated = preg_replace_callback('/([a-zA-Z0-9_]+)\s+IN\s*\((.+?)\)/i', function ($m) {
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
    public function save()
    {
        if ($this->isSaving) {
            return true;
        }
        if (!$this->filePath) {
            return false;
        }
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
            if (!$result) {
                error_log("SPPXDB::save - FAILED TO RENAME temp file '{$tempFile}' to '{$this->filePath}'");
            }
        } else {
            error_log("SPPXDB::save - DOMDocument::save FAILED for '{$tempFile}'");
        }

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

        $this->isSaving = false;
        if ($result !== false) {
            $this->logBinlog($this->tableName, 'mutation', ['file' => $this->filePath]);
        }
        return $result !== false;
    }

    protected function logBinlog($table, $action, $data)
    {
        $logPath = $this->dataDir . '/_binlog.jsonl';
        $entry = json_encode([
            'timestamp' => microtime(true),
            'table' => $table,
            'action' => $action,
            'data' => $data
        ]) . "\n";
        file_put_contents($logPath, $entry, FILE_APPEND);
    }

    public function replicateFrom($masterUrl, $dbName)
    {
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

    /**
     * Recursively evaluates a SQL-like expression (functions, literals, fields)
     * against a specific row data array.
     *
     * @param string $expr
     * @param array $row
     * @return mixed
     */
    protected function evaluateExpression($expr, $row)
    {
        $expr = trim($expr);

        // 1. Check if it's a literal string
        if (preg_match('/^[\'"](.*)[\'"]$/', $expr, $m)) {
            return $m[1];
        }

        // 2. Check if it's a numeric constant
        if (is_numeric($expr)) {
            return (float)$expr;
        }

        // 3. Check for NULL
        if (strcasecmp($expr, 'NULL') === 0) {
            return null;
        }

        // 4. Check for constant/zero-arg date-time functions
        if (strcasecmp($expr, 'NOW()') === 0) {
            return date('Y-m-d H:i:s');
        }
        if (strcasecmp($expr, 'CURDATE()') === 0 || strcasecmp($expr, 'CURRENT_DATE()') === 0) {
            return date('Y-m-d');
        }
        if (strcasecmp($expr, 'CURTIME()') === 0 || strcasecmp($expr, 'CURRENT_TIME()') === 0) {
            return date('H:i:s');
        }

        // 5. Check for function calls e.g. FUNC(args)
        if (preg_match('/^([a-zA-Z0-9_]+)\s*\((.*)\)$/is', $expr, $m)) {
            $func = strtoupper($m[1]);
            $argsStr = trim($m[2]);

            // Smart split arguments taking commas inside brackets and quotes into account
            $args = [];
            if ($argsStr !== '') {
                $depth = 0;
                $inQuote = false;
                $quoteChar = '';
                $current = '';
                for ($i = 0; $i < strlen($argsStr); $i++) {
                    $char = $argsStr[$i];
                    if (($char === "'" || $char === '"') && ($i === 0 || $argsStr[$i - 1] !== '\\')) {
                        if ($inQuote && $char === $quoteChar) {
                            $inQuote = false;
                        } elseif (!$inQuote) {
                            $inQuote = true;
                            $quoteChar = $char;
                        }
                    }
                    if (!$inQuote) {
                        if ($char === '(') {
                            $depth++;
                        }
                        if ($char === ')') {
                            $depth--;
                        }
                    }
                    if ($char === ',' && $depth === 0 && !$inQuote) {
                        $args[] = $this->evaluateExpression($current, $row);
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                }
                $args[] = $this->evaluateExpression($current, $row);
            }

            switch ($func) {
                // String Functions
                case 'CONCAT':
                    return implode('', array_map(fn ($a) => $a ?? '', $args));
                case 'UPPER':
                    return strtoupper((string)($args[0] ?? ''));
                case 'LOWER':
                    return strtolower((string)($args[0] ?? ''));
                case 'LENGTH':
                    return strlen((string)($args[0] ?? ''));
                case 'TRIM':
                    return trim((string)($args[0] ?? ''));
                case 'REVERSE':
                    return strrev((string)($args[0] ?? ''));
                case 'MD5':
                    return md5((string)($args[0] ?? ''));
                case 'SUBSTR':
                case 'SUBSTRING':
                    $str = (string)($args[0] ?? '');
                    $start = isset($args[1]) ? (int)$args[1] - 1 : 0; // SQL is 1-indexed
                    if ($start < 0) {
                        $start = 0;
                    }
                    $len = isset($args[2]) ? (int)$args[2] : null;
                    return $len !== null ? substr($str, $start, $len) : substr($str, $start);

                    // Logic & Control Flow
                case 'COALESCE':
                case 'IFNULL':
                    foreach ($args as $arg) {
                        if ($arg !== null && $arg !== '') {
                            return $arg;
                        }
                    }
                    return null;
                case 'IF':
                    return ($args[0] ?? false) ? ($args[1] ?? null) : ($args[2] ?? null);

                    // Math / Numeric Functions
                case 'ABS':
                    return abs((float)($args[0] ?? 0));
                case 'ROUND':
                    return round((float)($args[0] ?? 0), isset($args[1]) ? (int)$args[1] : 0);
                case 'CEIL':
                case 'CEILING':
                    return ceil((float)($args[0] ?? 0));
                case 'FLOOR':
                    return floor((float)($args[0] ?? 0));
                case 'POW':
                case 'POWER':
                    return pow((float)($args[0] ?? 0), (float)($args[1] ?? 0));
                case 'SQRT':
                    return sqrt((float)($args[0] ?? 0));
                case 'RAND':
                    return mt_rand() / mt_getrandmax();

                    // Date Extraction Functions
                case 'YEAR':
                    $time = strtotime((string)($args[0] ?? ''));
                    return $time !== false ? (int)date('Y', $time) : null;
                case 'MONTH':
                    $time = strtotime((string)($args[0] ?? ''));
                    return $time !== false ? (int)date('m', $time) : null;
                case 'DAY':
                    $time = strtotime((string)($args[0] ?? ''));
                    return $time !== false ? (int)date('d', $time) : null;
            }
        }

        // 6. Check for comparisons (e.g. A >= B) but only when not inside quotes
        if (preg_match('/^(.*?)\s*(>=|<=|!=|<>|>|<|=)\s*(.*)$/', $expr, $compMatch)) {
            $left = $this->evaluateExpression($compMatch[1], $row);
            $op = $compMatch[2];
            $right = $this->evaluateExpression($compMatch[3], $row);

            switch ($op) {
                case '=':   return $left == $right;
                case '!=':
                case '<>':  return $left != $right;
                case '>':   return $left > $right;
                case '<':   return $left < $right;
                case '>=':  return $left >= $right;
                case '<=':  return $left <= $right;
            }
        }

        // 7. Fallback: Treat as a column name
        return $row[$expr] ?? null;
    }

    public function getNumRows()
    {
        return is_array($this->lastResultSet) ? count($this->lastResultSet) : 0;
    }
}
