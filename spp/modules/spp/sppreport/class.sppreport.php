<?php
/**
 * SPP Report Engine
 * Handles schema introspection and dynamic SQL query building for custom reports.
 */

class SPPReport {
    private $db;

    public function __construct($externalConfig = null) {
        if ($externalConfig && !empty($externalConfig['dsn'])) {
            $user = $externalConfig['user'] ?? null;
            $pass = $externalConfig['pass'] ?? null;
            $pdo = new \PDO($externalConfig['dsn'], $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Create a mock SPPDB object using an anonymous class
            $this->db = new class($pdo) {
                private $pdo;
                public function __construct($pdo) { $this->pdo = $pdo; }
                public function getDriver() { return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME); }
                public function query($sql, $params = []) {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            };
        } else {
            if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                require_once __DIR__ . '/../sppdb/class.sppdb.php';
            }
            $this->db = new \SPPMod\SPPDB\SPPDB();
        }
    }

    public function getDriver() {
        return $this->db->getDriver();
    }

    /**
     * Introspect the database schema to get available tables and columns
     */
    public function getSchema() {
        $schema = [];
        // Support SQLite, MySQL, PgSQL schema queries
        $driver = $this->db->getDriver();
        
        try {
            if ($driver === 'sqlite') {
                $tables = $this->db->execute_query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($tables as $t) {
                    $tableName = $t['name'];
                    $columns = $this->db->execute_query("PRAGMA table_info(" . $tableName . ")");
                    $cols = array_map(function($c) { return $c['name']; }, $columns);
                    $schema[$tableName] = $cols;
                }
            } else if ($driver === 'mysql') {
                $tables = $this->db->execute_query("SHOW TABLES");
                foreach ($tables as $t) {
                    $tableName = array_values($t)[0];
                    $columns = $this->db->execute_query("SHOW COLUMNS FROM " . $tableName);
                    $cols = array_map(function($c) { return $c['Field']; }, $columns);
                    $schema[$tableName] = $cols;
                }
            } else if ($driver === 'pgsql') {
                $tables = $this->db->execute_query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'");
                foreach ($tables as $t) {
                    $tableName = $t['tablename'];
                    $columns = $this->db->execute_query("SELECT column_name FROM information_schema.columns WHERE table_name = ?", [$tableName]);
                    $cols = array_map(function($c) { return $c['column_name']; }, $columns);
                    $schema[$tableName] = $cols;
                }
            } else if ($driver === 'xdb') {
                $tables = $this->db->execute_query("SHOW TABLES");
                foreach ($tables as $t) {
                    $tableName = array_values($t)[0];
                    $columns = $this->db->execute_query("DESCRIBE " . $tableName);
                    $cols = array_map(function($c) { return $c['Field']; }, $columns);
                    $schema[$tableName] = $cols;
                }
            }
        } catch (Exception $e) {
            error_log("SPPReport Schema Introspection Error: " . $e->getMessage());
        }

        return $schema;
    }

    /**
     * Builds and executes a dynamic SQL query from a JSON configuration payload.
     * Configuration format:
     * {
     *   "table": "users",
     *   "columns": [
     *      {"field": "id", "aggregate": "COUNT", "alias": "Total Users"},
     *      {"field": "status", "aggregate": null, "alias": "Status"}
     *   ],
     *   "filters": {
     *      "logic": "AND",
     *      "conditions": [
     *          {"field": "age", "operator": ">", "value": 18},
     *          {
     *              "logic": "OR",
     *              "conditions": [
     *                  {"field": "role", "operator": "=", "value": "admin"},
     *                  {"field": "role", "operator": "=", "value": "manager"}
     *              ]
     *          }
     *      ]
     *   },
     *   "group_by": ["status"],
     *   "order_by": {"field": "Total Users", "direction": "DESC"},
     *   "limit": 100
     * }
     */
    public function runReport($config) {
        $table = $config['table'] ?? '';
        if (empty($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new Exception("Invalid base table.");
        }

        $params = [];
        $selects = [];
        $groupBy = [];

        // 1. Build SELECT
        foreach ($config['columns'] as $col) {
            $field = $col['field'];
            $aggregate = $col['aggregate'] ?? null;
            $alias = $col['alias'] ?? '';
            $aliasSql = $alias ? " AS \"" . str_replace('"', '\"', $alias) . "\"" : "";

            if ($aggregate === 'CUSTOM') {
                // Formula field. Ensure it only contains safe math/sql chars
                if (!preg_match('/^[a-zA-Z0-9_\.\s\(\)\+\-\*\/\,\']+$/', $field)) continue;
                $selects[] = $field . $aliasSql;
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_\.\*]+$/', $field)) continue;

            if ($aggregate && in_array(strtoupper($aggregate), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'])) {
                $selects[] = strtoupper($aggregate) . "(" . $field . ")" . $aliasSql;
            } else {
                $selects[] = $field . $aliasSql;
            }
        }
        
        $selectSql = empty($selects) ? "*" : implode(', ', $selects);

        // 1.5 Build JOINS
        $joinSql = "";
        if (!empty($config['joins'])) {
            $joinParts = [];
            foreach ($config['joins'] as $j) {
                $type = strtoupper($j['type'] ?? 'LEFT JOIN');
                if (!in_array($type, ['JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN'])) $type = 'LEFT JOIN';
                $jt = $j['table'] ?? '';
                $on = $j['on'] ?? '';
                // Basic sanitation to prevent injection in table/on names
                if (preg_match('/^[a-zA-Z0-9_]+$/', $jt) && preg_match('/^[a-zA-Z0-9_\.\s=]+$/', $on)) {
                    $joinParts[] = $type . " " . $jt . " ON " . $on;
                }
            }
            if (!empty($joinParts)) {
                $joinSql = " " . implode(" ", $joinParts);
            }
        }

        // 2. Build WHERE
        $whereSql = "";
        if (!empty($config['filters']) && !empty($config['filters']['conditions'])) {
            $parsedFilters = $this->parseFilters($config['filters'], $params);
            if (!empty($parsedFilters)) {
                $whereSql = " WHERE " . $parsedFilters;
            }
        }

        // 3. Build GROUP BY
        $groupBySql = "";
        if (!empty($config['group_by'])) {
            $safeGroups = [];
            foreach ($config['group_by'] as $g) {
                if (preg_match('/^[a-zA-Z0-9_\.]+$/', $g)) $safeGroups[] = $g;
            }
            if (!empty($safeGroups)) {
                $groupBySql = " GROUP BY " . implode(', ', $safeGroups);
            }
        }

        // 4. Build ORDER BY
        $orderBySql = "";
        if (!empty($config['order_by']) && !empty($config['order_by']['field'])) {
            // Can be alias or field name
            $dir = strtoupper($config['order_by']['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $orderBySql = " ORDER BY \"" . str_replace('"', '\"', $config['order_by']['field']) . "\" " . $dir;
        }

        // 5. Build LIMIT
        $limitSql = "";
        $limit = intval($config['limit'] ?? 100);
        if ($limit > 0) {
            $limitSql = " LIMIT " . $limit;
        }

        $sql = "SELECT " . $selectSql . " FROM " . $table . $joinSql . $whereSql . $groupBySql . $orderBySql . $limitSql;

        return [
            'sql' => $sql,
            'params' => $params,
            'data' => $this->db->execute_query($sql, $params)
        ];
    }

    /**
     * Recursively parses filters into SQL condition strings and binds params.
     */
    private function parseFilters($filterGroup, &$params) {
        if (empty($filterGroup['conditions'])) return "";

        $logic = strtoupper($filterGroup['logic'] ?? 'AND') === 'OR' ? ' OR ' : ' AND ';
        $parts = [];

        foreach ($filterGroup['conditions'] as $cond) {
            if (isset($cond['logic'])) {
                // Nested group
                $nested = $this->parseFilters($cond, $params);
                if ($nested) $parts[] = "(" . $nested . ")";
            } else {
                // Single condition
                $field = $cond['field'] ?? '';
                if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $field)) continue;

                $op = strtoupper($cond['operator'] ?? '=');
                $allowedOps = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];
                if (!in_array($op, $allowedOps)) $op = '=';

                $val = $cond['value'] ?? null;

                // RBAC Context Variable Replacement
                if ($val === '{{CURRENT_USER_ID}}') {
                    if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
                        $currentUser = \SPPMod\SPPAuth\SPPAuth::getCurrentUser();
                        $val = $currentUser['id'] ?? 0;
                    } else {
                        $val = 0;
                    }
                }

                if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                    $parts[] = $field . " " . $op;
                } else if ($op === 'IN' || $op === 'NOT IN') {
                    if (is_array($val) && !empty($val)) {
                        $placeholders = [];
                        foreach ($val as $v) {
                            $placeholders[] = '?';
                            $params[] = $v;
                        }
                        $parts[] = $field . " " . $op . " (" . implode(',', $placeholders) . ")";
                    }
                } else {
                    if ($op === 'LIKE') {
                        $val = "%" . $val . "%";
                    }
                    $parts[] = $field . " " . $op . " ?";
                    $params[] = $val;
                }
            }
        }

        return implode($logic, $parts);
    }
}
