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
                $aliasSql = " AS \"" . str_replace('"', '\"', $alias) . "\"";
            }

            if ($aggregate === 'CUSTOM') {
                if (!preg_match('/^[a-zA-Z0-9_\.\(\)\+\-\*\/\,]+$/', $field)) {
                    continue;
                }
                $selects[] = $field . $aliasSql;
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_\.\*]+$/', $field)) {
                continue;
            }

            if ($aggregate && in_array(strtoupper($aggregate), ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'])) {
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
        if (!empty($config['filters']) && !empty($config['filters']['conditions'])) {
            $parsedFilters = $this->parseFilters($config['filters'], $params);
            if (!empty($parsedFilters)) {
                $whereSql = " WHERE " . $parsedFilters;
            }
        }

        // 4. Build GROUP BY
        $groupBySql = "";
        if (!empty($config['group_by'])) {
            $safeGroups = [];
            foreach ($config['group_by'] as $g) {
                if (preg_match('/^[a-zA-Z0-9_\.]+$/', $g)) {
                    $safeGroups[] = $g;
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
            $orderBySql = " ORDER BY \"" . str_replace('"', '\"', $config['order_by']['field']) . "\" " . $dir;
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
                if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $field)) {
                    continue;
                }

                $op = strtoupper($cond['operator'] ?? '=');
                $allowedOps = ['=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];
                if (!in_array($op, $allowedOps)) {
                    $op = '=';
                }

                $val = $cond['value'] ?? null;

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
                    $columns = $this->db->execute_query("PRAGMA table_info(" . $tableName . ")");
                    $cols = array_map(function ($c) {
                        return $c['name'];
                    }, $columns);
                    $schema[$tableName] = $cols;
                }
            } else if ($driver === 'mysql') {
                $tables = $this->db->execute_query("SHOW TABLES");
                foreach ($tables as $t) {
                    $tableName = array_values($t)[0];
                    $columns = $this->db->execute_query("SHOW COLUMNS FROM " . $tableName);
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
                    $columns = $this->db->execute_query("DESCRIBE " . $tableName);
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

    public function runReport(array $config): array
    {
        $build = $this->queryBuilder->build($config);
        $sql = $build['sql'];
        $params = $build['params'];

        return [
            'sql' => $sql,
            'params' => $params,
            'data' => $this->db->execute_query($sql, $params)
        ];
    }

    /**
     * Executes the report and yields rows one by one for memory-efficient streaming.
     */
    public function streamReport(array $config): \Generator
    {
        $build = $this->queryBuilder->build($config);
        $sql = $build['sql'];
        $params = $build['params'];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }
}

if (!class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
    require_once __DIR__ . '/W3CTraceContext.php';
}
