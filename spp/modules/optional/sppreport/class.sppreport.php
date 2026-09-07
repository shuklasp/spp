<?php
/**
 * SPP Report Engine
 * Handles schema introspection, secure SQL query building, and execution for custom reports.
 */

use SPP\Core\SchemaValidator;

if (!class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
    require_once __DIR__ . '/../sppdb/class.sppdb.php';
}
if (!class_exists('\\SPP\\Core\\SchemaValidator')) {
    require_once __DIR__ . '/../../../../spp/core/class.schemavalidator.php';
}

/**
 * Class ExternalDatabaseConnection
 * Provides a clean, object-oriented database connection wrapper for external DSNs,
 * fully compatible with SPPDB methods used by SPPReport.
 */
class ExternalDatabaseConnection extends \SPPMod\SPPDB\SPPDB
{
    private \PDO $externalPdo;
    private string $driverName;

    public function __construct(string $dsn, ?string $user = null, ?string $pass = null)
    {
        $this->externalPdo = new \PDO($dsn, $user, $pass);
        $this->externalPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->driverName = $this->externalPdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function getDriver(): ?string
    {
        return $this->driverName;
    }

    public function getPDO(): ?\PDO
    {
        return $this->externalPdo;
    }

    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        $stmt = $this->externalPdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function execute_query($sql, $values = [])
    {
        $stmt = $this->externalPdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function prepare(string $query, array $options = [])
    {
        return $this->externalPdo->prepare($query, $options);
    }
}

/**
 * Class ReportQueryBuilder
 * Modular query builder responsible for parsing configurations, sanitizing identifiers,
 * and building safe SQL statements.
 */
class ReportQueryBuilder
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function build(array $config): array
    {
        $table = $config['table'] ?? '';
        if (empty($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \Exception("Invalid base table.");
        }

        $params = [];
        $selects = [];

        // 1. Build SELECT
        foreach ($config['columns'] as $col) {
            $field = $col['field'];
            $aggregate = $col['aggregate'] ?? null;
            $alias = $col['alias'] ?? '';
            
            // Safe alias using SchemaValidator if available, or fallback backticks/quotes
            $aliasSql = "";
            if ($alias) {
                try {
                    $escapedAlias = SchemaValidator::escapeIdentifier($alias);
                    $aliasSql = " AS " . $escapedAlias;
                } catch (\Exception $e) {
                    $aliasSql = " AS \"" . str_replace('"', '""', $alias) . "\"";
                }
            }

            if ($aggregate === 'CUSTOM') {
                if (!preg_match('/^[a-zA-Z0-9_\.\(\)\+\-\*\/\,\s]+$/', $field)) {
                    continue;
                }
                // Disallow subqueries and dangerous DML/DDL keywords
                if (preg_match('/(?i)\b(SELECT|FROM|JOIN|WHERE|UNION|UPDATE|DELETE|INSERT|DROP|EXEC|ALTER)\b/', $field)) {
                    continue;
                }
                $selects[] = $field . $aliasSql;
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_\.\*\->]+$/', $field)) {
                continue;
            }

            if ($aggregate && in_array(strtoupper($aggregate), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'YEAR', 'MONTH', 'DATE', 'DAY', 'HOUR'])) {
                $selects[] = strtoupper($aggregate) . "(" . $field . ")" . $aliasSql;
            } else {
                $selects[] = $field . $aliasSql;
            }
        }

        $selectSql = empty($selects) ? "*" : implode(', ', $selects);

        // 2. Build JOINS
        $joinSql = "";
        if (!empty($config['joins'])) {
            $joinParts = [];
            foreach ($config['joins'] as $j) {
                $type = strtoupper($j['type'] ?? 'LEFT JOIN');
                if (!in_array($type, ['JOIN', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN'])) {
                    $type = 'LEFT JOIN';
                }
                $jt = $j['table'] ?? '';
                $on = $j['on'] ?? '';
                if (preg_match('/^[a-zA-Z0-9_]+$/', $jt) && preg_match('/^[a-zA-Z0-9_\.\s=]+$/', $on)) {
                    try {
                        $escapedTable = SchemaValidator::escapeIdentifier($jt);
                    } catch (\Exception $e) {
                        $escapedTable = $jt;
                    }
                    $joinParts[] = $type . " " . $escapedTable . " ON " . $on;
                }
            }
            if (!empty($joinParts)) {
                $joinSql = " " . implode(" ", $joinParts);
            }
        }

        // 3. Build WHERE
        $whereSql = "";
        $parsedFilters = "";
        if (!empty($config['filters'])) {
            $parsedFilters = $this->parseFilters($config['filters'], $params);
        }

        // Apply Global Scopes unconditionally
        $globalScopes = [];
        if (class_exists('\\SPPMod\\SPPReport\\GlobalScopeRegistry')) {
            foreach (\SPPMod\SPPReport\GlobalScopeRegistry::getScopes() as $field => $val) {
                // Ensure field is a safe identifier
                if (preg_match('/^[a-zA-Z0-9_\.\->]+$/', $field)) {
                    $globalScopes[] = $field . " = ?";
                    $params[] = $val;
                }
            }
        }

        if (!empty($globalScopes)) {
            $scopeSql = implode(' AND ', $globalScopes);
            if ($parsedFilters) {
                $whereSql = " WHERE (" . $scopeSql . ") AND (" . $parsedFilters . ")";
            } else {
                $whereSql = " WHERE " . $scopeSql;
            }
        } else if ($parsedFilters) {
            $whereSql = " WHERE " . $parsedFilters;
        }

        // 4. Build GROUP BY
        $groupBySql = "";
        if (!empty($config['group_by'])) {
            $safeGroups = [];
            foreach ($config['group_by'] as $g) {
                if (preg_match('/^([a-zA-Z0-9_]+\()?([a-zA-Z0-9_\.\->]+)\)?$/', $g, $m)) {
                    $func = strtoupper($m[1] ?? '');
                    $innerField = $m[2];
                    if ($func) {
                        $allowedFuncs = ['YEAR(', 'MONTH(', 'DATE(', 'DAY(', 'HOUR('];
                        if (in_array($func, $allowedFuncs)) {
                            $safeGroups[] = $func . $innerField . ")";
                        }
                    } else {
                        $safeGroups[] = $innerField;
                    }
                }
            }
            if (!empty($safeGroups)) {
                $groupBySql = " GROUP BY " . implode(', ', $safeGroups);
            }
        }

        // 5. Build ORDER BY
        $orderBySql = "";
        if (!empty($config['order_by']) && !empty($config['order_by']['field'])) {
            $dir = strtoupper($config['order_by']['direction'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            try {
                $escapedOrderBy = SchemaValidator::escapeIdentifier($config['order_by']['field']);
            } catch (\Exception $e) {
                $escapedOrderBy = "\"" . str_replace('"', '""', $config['order_by']['field']) . "\"";
            }
            $orderBySql = " ORDER BY " . $escapedOrderBy . " " . $dir;
        }

        // 6. Build LIMIT
        $limitSql = "";
        $limit = intval($config['limit'] ?? 100);
        if ($limit > 0) {
            $limitSql = " LIMIT " . $limit;
        }

        try {
            $escapedBaseTable = SchemaValidator::escapeIdentifier($table);
        } catch (\Exception $e) {
            $escapedBaseTable = $table;
        }

        $sql = "SELECT " . $selectSql . " FROM " . $escapedBaseTable . $joinSql . $whereSql . $groupBySql . $orderBySql . $limitSql;

        return [
            'sql' => $sql,
            'params' => $params
        ];
    }

    private function parseFilters(array $filterGroup, array &$params): string
    {
        if (empty($filterGroup['conditions'])) {
            return "";
        }

        $logic = strtoupper($filterGroup['logic'] ?? 'AND') === 'OR' ? ' OR ' : ' AND ';
        $parts = [];

        foreach ($filterGroup['conditions'] as $cond) {
            if (isset($cond['logic'])) {
                $nested = $this->parseFilters($cond, $params);
                if ($nested) {
                    $parts[] = "(" . $nested . ")";
                }
            } else {
                $field = $cond['field'] ?? '';
                if (!preg_match('/^[a-zA-Z0-9_\.\->]+$/', $field)) {
                    continue;
                }

                $op = strtoupper($cond['operator'] ?? '=');
                $allowedOps = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL', 'BETWEEN', 'NOT BETWEEN', 'REGEXP', 'NOT REGEXP'];
                if (!in_array($op, $allowedOps)) {
                    $op = '=';
                }

                $val = $cond['value'] ?? null;

                if (is_string($val) && str_starts_with($val, '{{') && str_ends_with($val, '}}')) {
                    $resolved = \SPPMod\SPPReport\MacroRegistry::resolve($val);
                    if ($resolved !== null) {
                        $val = $resolved;
                    } else {
                        // Unrecognized macro, fallback to 0 for safety to prevent breaking SQL
                        $val = 0;
                    }
                }

                if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                    $parts[] = $field . " " . $op;
                } else if ($op === 'BETWEEN' || $op === 'NOT BETWEEN') {
                    if (is_array($val) && count($val) === 2) {
                        $parts[] = $field . " " . $op . " ? AND ?";
                        $params[] = $val[0];
                        $params[] = $val[1];
                    }
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

/**
 * Class SPPReport
 * Main engine orchestrating database connections, schema introspection,
 * and query execution/streaming.
 */
class SPPReport
{
    private $db;
    private ReportQueryBuilder $queryBuilder;

    public function __construct(?array $externalConfig = null)
    {
        if ($externalConfig && !empty($externalConfig['dsn'])) {
            $user = $externalConfig['user'] ?? null;
            $pass = $externalConfig['pass'] ?? null;
            $this->db = new ExternalDatabaseConnection($externalConfig['dsn'], $user, $pass);
        } else {
            $this->db = new \SPPMod\SPPDB\SPPDB();
        }
        $this->queryBuilder = new ReportQueryBuilder($this->db);
    }

    public function getDriver(): ?string
    {
        return $this->db->getDriver();
    }

    public function getSchema(): array
    {
        $schema = [];
        $driver = $this->db->getDriver();

        try {
            if ($driver === 'sqlite') {
                $tables = $this->db->execute_query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($tables as $t) {
                    $tableName = $t['name'];
                    try {
                        $escapedTableName = SchemaValidator::escapeIdentifier($tableName);
                    } catch (\Exception $e) {
                        $escapedTableName = '"' . str_replace('"', '""', $tableName) . '"';
                    }
                    $columns = $this->db->execute_query("PRAGMA table_info(" . $escapedTableName . ")");
                    $cols = array_map(function ($c) {
                        return $c['name'];
                    }, $columns);
                    $schema[$tableName] = $cols;
                }
            } else if ($driver === 'mysql') {
                $tables = $this->db->execute_query("SHOW TABLES");
                foreach ($tables as $t) {
                    $tableName = array_values($t)[0];
                    try {
                        $escapedTableName = SchemaValidator::escapeIdentifier($tableName);
                    } catch (\Exception $e) {
                        $escapedTableName = '`' . str_replace('`', '``', $tableName) . '`';
                    }
                    $columns = $this->db->execute_query("SHOW COLUMNS FROM " . $escapedTableName);
                    $cols = array_map(function ($c) {
                        return $c['Field'];
                    }, $columns);
                    $schema[$tableName] = $cols;
                }
            } else if ($driver === 'pgsql') {
                $tables = $this->db->execute_query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'");
                foreach ($tables as $t) {
                    $tableName = $t['tablename'];
                    $columns = $this->db->execute_query("SELECT column_name FROM information_schema.columns WHERE table_name = ?", [$tableName]);
                    $cols = array_map(function ($c) {
                        return $c['column_name'];
                    }, $columns);
                    $schema[$tableName] = $cols;
                }
            } else if ($driver === 'xdb') {
                $tables = $this->db->execute_query("SHOW TABLES");
                foreach ($tables as $t) {
                    $tableName = array_values($t)[0];
                    try {
                        $escapedTableName = SchemaValidator::escapeIdentifier($tableName);
                    } catch (\Exception $e) {
                        $escapedTableName = '"' . str_replace('"', '""', $tableName) . '"';
                    }
                    $columns = $this->db->execute_query("DESCRIBE " . $escapedTableName);
                    $cols = array_map(function ($c) {
                        return $c['Field'];
                    }, $columns);
                    $schema[$tableName] = $cols;
                }
            }
        } catch (\Exception $e) {
            error_log("SPPReport Schema Introspection Error: " . $e->getMessage());
        }

        return $schema;
    }

    public function estimateCost(array $config): array
    {
        $build = $this->queryBuilder->build($config);
        $sql = $build['sql'];
        $params = $build['params'];
        
        // Only run EXPLAIN if we are on MySQL or PostgreSQL
        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['mysql', 'pgsql'])) {
            return ['status' => 'unknown', 'cost' => 0];
        }

        try {
            $stmt = $this->db->prepare("EXPLAIN " . $sql);
            $stmt->execute($params);
            $explanation = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $totalRows = 0;
            $missingIndex = false;
            
            foreach ($explanation as $row) {
                // MySQL parsing
                if (isset($row['rows'])) {
                    $totalRows += (int)$row['rows'];
                }
                if (isset($row['type']) && strtolower($row['type']) === 'all') {
                    $missingIndex = true;
                }
                
                // PostgreSQL parsing (typically returns a single string like "Seq Scan on table (cost=0.00..12.34 rows=123 width=4)")
                if (isset($row['QUERY PLAN'])) {
                    if (preg_match('/rows=(\d+)/', $row['QUERY PLAN'], $m)) {
                        $totalRows += (int)$m[1];
                    }
                    if (stripos($row['QUERY PLAN'], 'Seq Scan') !== false) {
                        $missingIndex = true;
                    }
                }
            }

            // Arbitrary heuristic: if it scans > 1 million rows without an index, it's a critical cost
            $severity = 'low';
            if ($totalRows > 100000) $severity = 'medium';
            if ($totalRows > 1000000 && $missingIndex) $severity = 'high';
            if ($totalRows > 5000000) $severity = 'critical';

            return [
                'status' => 'success',
                'total_rows_scanned' => $totalRows,
                'missing_index' => $missingIndex,
                'severity' => $severity
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getUserRoles(): array
    {
        $roleNames = [];
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            $user = \SPPMod\SPPAuth\SPPAuth::user();
            if ($user && method_exists($user, 'getRoles')) {
                $roleIds = $user->getRoles();
                if (!empty($roleIds)) {
                    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
                    $roles = $this->db->execute_query("SELECT name FROM " . \SPPMod\SPPDB\SPPDB::sppTable('roles') . " WHERE id IN ($placeholders)", $roleIds);
                    $roleNames = array_column($roles, 'name');
                }
            }
        }
        return $roleNames;
    }

    public function runReport(array $config): array
    {
        if (class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
            \SPPMod\SPPReport\W3CTraceContext::startSpan('report_run', ['table' => $config['table'] ?? 'unknown']);
        }

        $build = $this->queryBuilder->build($config);
        $sql = $build['sql'];
        $params = $build['params'];
        
        $data = $this->db->execute_query($sql, $params);

        if (!empty($config['masking_rules'])) {
            if (!class_exists('\\SPPMod\\SPPReport\\Services\\DataMasker')) {
                require_once __DIR__ . '/services/DataMasker.php';
            }
            $masker = new \SPPMod\SPPReport\Services\DataMasker($config, $this->getUserRoles());
            if ($masker->isMaskingActive()) {
                foreach ($data as &$row) {
                    $row = $masker->maskRow($row);
                }
            }
        }

        return [
            'sql' => $sql,
            'params' => $params,
            'data' => $data
        ];
    }

    /**
     * Executes the report and yields rows one by one for memory-efficient streaming.
     */
    public function streamReport(array $config): \Generator
    {
        if (class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
            \SPPMod\SPPReport\W3CTraceContext::startSpan('report_stream', ['table' => $config['table'] ?? 'unknown']);
        }

        $build = $this->queryBuilder->build($config);
        $sql = $build['sql'];
        $params = $build['params'];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $masker = null;
        if (!empty($config['masking_rules'])) {
            if (!class_exists('\\SPPMod\\SPPReport\\Services\\DataMasker')) {
                require_once __DIR__ . '/services/DataMasker.php';
            }
            $masker = new \SPPMod\SPPReport\Services\DataMasker($config, $this->getUserRoles());
        }

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($masker && $masker->isMaskingActive()) {
                $row = $masker->maskRow($row);
            }
            yield $row;
        }
    }
}

if (!class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
    require_once __DIR__ . '/W3CTraceContext.php';
}

if (!class_exists('\\SPPMod\\SPPReport\\MacroRegistry')) {
    require_once __DIR__ . '/MacroRegistry.php';
}

if (!class_exists('\\SPPMod\\SPPReport\\GlobalScopeRegistry')) {
    require_once __DIR__ . '/GlobalScopeRegistry.php';
}
