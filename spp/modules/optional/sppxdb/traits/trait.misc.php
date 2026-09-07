<?php

namespace SPPMod\SPPXDB;

trait XDB_Misc
{
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

    protected function escapeXPathString($value)
    {
        if (strpos($value, "'") === false) {
            return "'" . $value . "'";
        }
        if (strpos($value, '"') === false) {
            return '"' . $value . '"';
        }
        return "concat('" . str_replace("'", "', '\"', '", $value) . "')";
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
