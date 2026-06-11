<?php

namespace SPPMod\SPPXDB;

trait XDB_Sqlparser
{
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
        $logDir = defined('SPP_LOG_DIR') ? SPP_LOG_DIR : dirname(__DIR__, 4) . '/var/logs';
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
            if ($cols->length > 0) {
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
            } else {
                // Fallback: get keys from first row
                $nodes = $this->xpath->query("//row");
                if ($nodes && $nodes->length > 0) {
                    $row = $this->nodeToArray($nodes->item(0));
                    $keys = array_filter(array_keys($row), function ($k) {
                        return $k[0] !== '@' && $k !== 'history';
                    });
                    if (in_array('id', $keys)) {
                        $results[] = [
                            'Field'   => 'id',
                            'Type'    => 'VARCHAR',
                            'Null'    => 'NO',
                            'Key'     => 'PRI',
                            'Default' => null,
                            'Extra'   => ''
                        ];
                    }
                    foreach ($keys as $k) {
                        if ($k === 'id') continue;
                        $results[] = [
                            'Field'   => $k,
                            'Type'    => 'TEXT',
                            'Null'    => 'YES',
                            'Key'     => '',
                            'Default' => null,
                            'Extra'   => ''
                        ];
                    }
                } else {
                    error_log("SPPXDB DESCRIBE fallback: No rows found for table $tableName");
                }
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
            return $this->escapeXPathString($val);
        }, $translated);

        // Handle named parameters (:name)
        if (preg_match_all('/(:[a-zA-Z0-9_]+)/', $translated, $namedMatches)) {
            foreach ($namedMatches[1] as $pName) {
                if (array_key_exists($pName, $params)) {
                    $translated = str_replace($pName, $this->escapeXPathString($params[$pName]), $translated);
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

        // Handle IS NOT NULL
        $translated = preg_replace('/([a-zA-Z0-9_@]+)\s+IS\s+NOT\s+NULL/i', '($1 and $1!=\'\')', $translated);

        // Handle IS NULL
        $translated = preg_replace('/([a-zA-Z0-9_@]+)\s+IS\s+NULL/i', '(not($1) or $1=\'\')', $translated);

        // Basic operator replacements
        $translated = str_replace('<>', '!=', $translated);

        // Range optimization: x > 5 -> number(x) > 5
        $translated = preg_replace('/([a-zA-Z0-9_]+)\s*(>=|>|<=|<)\s*([0-9\.]+)/', 'number($1) $2 $3', $translated);
        $translated = preg_replace('/\s+AND\s+/i', ' and ', $translated);
        $translated = preg_replace('/\s+OR\s+/i', ' or ', $translated);

        return $translated;
    }

}
