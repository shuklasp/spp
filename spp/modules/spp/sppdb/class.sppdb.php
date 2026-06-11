<?php

namespace SPPMod\SPPDB;

/*require_once("class.sppconfig.php");
require_once 'class.sppobject.php';*/
//\SPP\Module::initWS('sppdb');
/**
 * class SPPDB
 * Handles database transations in the system.
 *
 * @author Satya Prakash Shukla
 */

class SPPDB
{
    /** @var array<\PDO> Shared connections pool indexed by connection hash */
    private static array $sharedConnections = [];

    /** @var string|null The resolved database type (e.g. mysql, sqlite, xdb) */
    public ?string $dbtype = null;

    /** @var string|null The resolved database name */
    public ?string $dbname = null;

    /**
     * Returns the current database driver name.
     */
    public function getDriver(): ?string
    {
        return $this->dbtype;
    }

    /**
     * Returns the appropriate compiler for the current database dialect.
     */
    public function getCompiler(): \SPPMod\SPPInterDB\Compilers\CompilerInterface
    {
        if (!interface_exists('\\SPPMod\\SPPInterDB\\Compilers\\CompilerInterface')) {
            require_once __DIR__ . '/Compilers/CompilerInterface.php';
        }

        switch ($this->dbtype) {
            case 'pgsql':
            case 'postgres':
                if (!class_exists('\\SPPMod\\SPPInterDB\\Compilers\\PostgresCompiler')) {
                    require_once __DIR__ . '/Compilers/PostgresCompiler.php';
                }
                return new \SPPMod\SPPInterDB\Compilers\PostgresCompiler();
            case 'sqlite':
                if (!class_exists('\\SPPMod\\SPPInterDB\\Compilers\\SQLiteCompiler')) {
                    require_once __DIR__ . '/Compilers/SQLiteCompiler.php';
                }
                return new \SPPMod\SPPInterDB\Compilers\SQLiteCompiler();
            case 'mysql':
            default:
                if (!class_exists('\\SPPMod\\SPPInterDB\\Compilers\\MySQLCompiler')) {
                    require_once __DIR__ . '/Compilers/MySQLCompiler.php';
                }
                return new \SPPMod\SPPInterDB\Compilers\MySQLCompiler();
        }
    }

    /**
     * Resolves a table name with current context's prefix, supporting shared group inheritance.
     *
     * @param string $tname
     * @return string
     */
    public static function sppTable(string $tname): string
    {
        $settings = self::loadGlobalSettings();
        $context = \SPP\Scheduler::getContext();
        $appMeta = $settings['apps'][$context] ?? null;

        $prefix = null;

        // 1. Try resolving via Shared Group inheritance
        if ($appMeta && !empty($appMeta['shared_group'])) {
            $prefix = self::resolveSharedPrefix($tname, $appMeta['shared_group'], $settings['shared_groups'] ?? []);
        }

        // 2. Fallback to App-specific prefix if not shared
        if ($prefix === null) {
            if ($appMeta && isset($appMeta['table_prefix'])) {
                $prefix = $appMeta['table_prefix'];
            } else {
                $prefix = \SPP\Module::getConfig('table_prefix', 'sppdb');
            }
        }

        // 3. Global Default Fallback
        if ($prefix === false && $context !== 'default') {
            $prefix = \SPP\Module::getConfig('table_prefix', 'sppdb', 'default');
        }

        $finalTable = ($prefix ?: '') . $tname;
        // error_log("SPPDB DEBUG: Resolved table '$tname' to '$finalTable' for context '$context' with prefix '$prefix'");
        return $finalTable;
    }

    /**
     * Recursively resolves a prefix for a table through shared group inheritance.
     */
    private static function resolveSharedPrefix(string $tname, string $groupName, array $groups): ?string
    {
        if (!isset($groups[$groupName])) {
            return null;
        }

        $group = $groups[$groupName];

        // Normalize table name for comparison (remove prefix if it's already there or handle as entity name)
        // For simplicity, we assume $tname corresponds to names in the 'entities' list
        $entities = $group['entities'] ?? [];
        if (in_array($tname, $entities)) {
            return $group['table_prefix'] ?? null;
        }

        // Walk up inheritance
        if (!empty($group['extends'])) {
            return self::resolveSharedPrefix($tname, $group['extends'], $groups);
        }

        return null;
    }

    /**
     * Helper to load global settings.
     */
    private static function loadGlobalSettings(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $path = (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 3)) . '/etc/global-settings.yml';
        if (!file_exists($path)) {
            $cached = ['apps' => [], 'shared_groups' => []];
            return $cached;
        }

        try {
            // Use Symfony YAML if available
            if (class_exists('\\Symfony\\Component\\Yaml\\Yaml')) {
                $cached = \Symfony\Component\Yaml\Yaml::parseFile($path);
            } else {
                // Low-level fallback or error
                $cached = ['apps' => [], 'shared_groups' => []];
            }
        } catch (\Exception $e) {
            $cached = ['apps' => [], 'shared_groups' => []];
        }

        return is_array($cached) ? $cached : ['apps' => [], 'shared_groups' => []];
    }

    /** @var \SPPMod\SPPInterDB\DBAdapter The active database adapter */
    private \SPPMod\SPPInterDB\DBAdapter $adapter;

    private $numrows;

    /**
     * public function __construct
     *
     * Creates or reuses a database connection, supporting per-app overrides, sharding, and read/write splitting.
     */
    public function __construct($dburl = null, $dbuser = null, $dbpasswd = null, $options = null, bool $shared = true, ?string $shardKey = null)
    {
        try {
            $url = null;
            $settings = self::loadGlobalSettings();
            $context = \SPP\Scheduler::getContext();
            $dbOverride = $settings['apps'][$context]['db_config'] ?? null;

            $dbtype = null;
            $dbname = null;

            if ($dburl == null) {
                if ($dbOverride) {
                    $dbtype = $dbOverride['dbtype'] ?? \SPP\Module::getConfig('dbtype', 'sppdb');
                    if ($dbtype === 'sqlite') {
                        $sqlite_path = $dbOverride['sqlite_path'] ?? \SPP\Module::getConfig('sqlite_path', 'sppdb');
                        $project_root = defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4);
                        $url = 'sqlite:' . ($sqlite_path === ':memory:' ? ':memory:' : ($project_root . '/' . ($sqlite_path ?: 'var/db/school.sqlite')));
                        $dbuser = 'root'; // Dummy for SQLite
                    } else {
                        // Sharding
                        $shardPrefix = '';
                        if ($shardKey !== null) {
                            $totalShards = $dbOverride['total_shards'] ?? \SPP\Module::getConfig('total_shards', 'sppdb');
                            if ($totalShards > 1) {
                                $shardIndex = crc32($shardKey) % $totalShards;
                                $shardPrefix = "shard_{$shardIndex}_";
                            }
                        }

                        $dbhost = $dbOverride[$shardPrefix . 'dbhost'] ?? \SPP\Module::getConfig($shardPrefix . 'dbhost', 'sppdb');
                        $dbname = $dbOverride[$shardPrefix . 'dbname'] ?? \SPP\Module::getConfig($shardPrefix . 'dbname', 'sppdb');
                        $url = $dbtype . ':host=' . $dbhost . ';dbname=' . $dbname;
                        $dbuser = $dbOverride[$shardPrefix . 'dbuser'] ?? \SPP\Module::getConfig($shardPrefix . 'dbuser', 'sppdb');
                        $dbpasswd = $dbOverride[$shardPrefix . 'dbpasswd'] ?? \SPP\Module::getConfig($shardPrefix . 'dbpasswd', 'sppdb');

                        // Read Replica Support
                        $read_dbhost = $dbOverride[$shardPrefix . 'read_dbhost'] ?? \SPP\Module::getConfig($shardPrefix . 'read_dbhost', 'sppdb');
                        if ($read_dbhost) {
                            $read_url = $dbtype . ':host=' . $read_dbhost . ';dbname=' . $dbname;
                            $read_dbuser = $dbOverride[$shardPrefix . 'read_dbuser'] ?? \SPP\Module::getConfig($shardPrefix . 'read_dbuser', 'sppdb') ?: $dbuser;
                            $read_dbpasswd = $dbOverride[$shardPrefix . 'read_dbpasswd'] ?? \SPP\Module::getConfig($shardPrefix . 'read_dbpasswd', 'sppdb') ?: $dbpasswd;
                        }
                    }
                } else {
                    $dbtype = \SPP\Module::getConfig('dbtype', 'sppdb');
                    if ($dbtype === 'sqlite') {
                        $sqlite_path = \SPP\Module::getConfig('sqlite_path', 'sppdb');
                        $project_root = defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4);
                        $url = 'sqlite:' . ($sqlite_path === ':memory:' ? ':memory:' : ($project_root . '/' . ($sqlite_path ?: 'var/db/school.sqlite')));
                        $dbuser = 'root'; // Dummy for SQLite
                    } else {
                        // Sharding
                        $shardPrefix = '';
                        if ($shardKey !== null) {
                            $totalShards = \SPP\Module::getConfig('total_shards', 'sppdb');
                            if ($totalShards > 1) {
                                $shardIndex = crc32($shardKey) % $totalShards;
                                $shardPrefix = "shard_{$shardIndex}_";
                            }
                        }

                        $dbhost = \SPP\Module::getConfig($shardPrefix . 'dbhost', 'sppdb');
                        $dbname = \SPP\Module::getConfig($shardPrefix . 'dbname', 'sppdb');
                        $url = ($dbtype && $dbhost && $dbname) ? ($dbtype . ':host=' . $dbhost . ';dbname=' . $dbname) : null;
                        $dbuser = \SPP\Module::getConfig($shardPrefix . 'dbuser', 'sppdb');
                        $dbpasswd = \SPP\Module::getConfig($shardPrefix . 'dbpasswd', 'sppdb');

                        // Read Replica Support
                        $read_dbhost = \SPP\Module::getConfig($shardPrefix . 'read_dbhost', 'sppdb');
                        if ($read_dbhost) {
                            $read_url = $dbtype . ':host=' . $read_dbhost . ';dbname=' . $dbname;
                            $read_dbuser = \SPP\Module::getConfig($shardPrefix . 'read_dbuser', 'sppdb') ?: $dbuser;
                            $read_dbpasswd = \SPP\Module::getConfig($shardPrefix . 'read_dbpasswd', 'sppdb') ?: $dbpasswd;
                        }
                    }
                }
            } else {
                $url = $dburl;
                $dbuser = ($dbuser == null) ? \SPP\Module::getConfig('dbuser', 'sppdb') : $dbuser;
                $dbpasswd = ($dbpasswd == null) ? \SPP\Module::getConfig('dbpasswd', 'sppdb') : $dbpasswd;
            }

            if ($url && preg_match('/^([a-z0-9]+):/', $url, $m)) {
                $this->dbtype = $m[1];
            } elseif ($dbtype) {
                $this->dbtype = $dbtype;
            }

            $this->dbname = $dbname ?: 'default';

            // -- Adapter Initialization --
            $dbEngine = \SPP\Module::getConfig('db_engine', 'sppdb');
            // error_log("SPPDB::__construct: Detected engine " . ($dbEngine ?: 'null') . " for dbtype " . ($this->dbtype ?: 'null'));
            if ($dbEngine === 'sppxdb' || $this->dbtype === 'xdb') {
                $xdbFile = dirname(__DIR__) . '/sppxdb/class.sppxdb.php';
                if (file_exists($xdbFile)) {
                    require_once $xdbFile;
                }

                $xdb = new \SPPMod\SPPXDB\SPP_XDB($this->dbname ?: 'default');
                $this->adapter = new \SPPMod\SPPInterDB\XDBAdapter($xdb);
                return;
            }

            // Diagnostic validation for PDO
            if (!$url || !$dbuser) {
                $configPath = \SPP\Module::getExpectedConfigPath('sppdb');
                $missing = [];
                if (!isset($dbhost)) {
                    $missing[] = 'dbhost';
                }
                if (!isset($dbname)) {
                    $missing[] = 'dbname';
                }
                if (!$dbuser) {
                    $missing[] = 'dbuser';
                }
                $missingStr = implode(', ', $missing);

                throw new \SPP\SPPException("Database configuration properties ($missingStr) are not defined in {$configPath}. Please check your configuration.");
            }

            // PDO Connection Sharing
            $key = null;
            $pdo = null;
            if ($shared) {
                $key = md5(serialize([$url, $dbuser, $dbpasswd, $options]));
                if (isset(self::$sharedConnections[$key])) {
                    $pdo = self::$sharedConnections[$key];
                }
            }

            if (!$pdo) {
                // error_log("Connecting to " . $url);
                if (!is_array($options)) {
                    $options = [];
                }
                
                // Add persistent connection if configured
                $persist = $dbOverride['persist_connection'] ?? \SPP\Module::getConfig('persist_connection', 'sppdb');
                if ($persist) {
                    $options[\PDO::ATTR_PERSISTENT] = true;
                }

                if ($dbuser == null && $dbpasswd == null && empty($options)) {
                    $pdo = new \PDO($url);
                } elseif (empty($options)) {
                    $pdo = new \PDO($url, $dbuser, $dbpasswd);
                } else {
                    $pdo = new \PDO($url, $dbuser, $dbpasswd, $options);
                }
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                if ($shared && $key) {
                    self::$sharedConnections[$key] = $pdo;
                }
            }

            // Init Read PDO if configured
            $readPdo = null;
            if (isset($read_url)) {
                $readKey = null;
                if ($shared) {
                    $readKey = md5(serialize([$read_url, $read_dbuser, $read_dbpasswd, $options]));
                    if (isset(self::$sharedConnections[$readKey])) {
                        $readPdo = self::$sharedConnections[$readKey];
                    }
                }
                if (!$readPdo) {
                    $readPdo = new \PDO($read_url, $read_dbuser, $read_dbpasswd, $options ?? []);
                    $readPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    if ($shared && $readKey) {
                        self::$sharedConnections[$readKey] = $readPdo;
                    }
                }
            }

            $this->adapter = new \SPPMod\SPPInterDB\PDOAdapter($pdo, $readPdo);

        } catch (\Exception $e) {
            // error_log("Database Connection Error: " . $e->getMessage());
            throw new \SPP\SPPException("Database Connection Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public function isXDB(): bool
    {
        return $this->adapter instanceof \SPPMod\SPPInterDB\XDBAdapter;
    }

    /**
     * Entry point for the fluent Query Builder.
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Returns metadata for all routeable entities in the current context.
     */
    public static function getRouteEntities(): array
    {
        $entities = \SPP\Module::getConfig('route_entities', 'sppdb') ?: [];
        $settings = self::loadGlobalSettings();
        $context = \SPP\Scheduler::getContext();
        $appMeta = $settings['apps'][$context] ?? null;

        if ($appMeta && !empty($appMeta['shared_group'])) {
            $sharedEntities = self::resolveRouteEntities($appMeta['shared_group'], $settings['shared_groups'] ?? []);
            $entities = array_merge($sharedEntities, $entities);
        }

        return $entities;
    }

    private static function resolveRouteEntities(string $groupName, array $groups): array
    {
        if (!isset($groups[$groupName])) {
            return [];
        }
        $group = $groups[$groupName];
        $entities = $group['route_entities'] ?? [];
        if (!empty($group['extends'])) {
            $entities = array_merge(self::resolveRouteEntities($group['extends'], $groups), $entities);
        }
        return $entities;
    }

    /**
     * Returns the underlying database adapter instance.
     */
    public function getAdapter(): \SPPMod\SPPInterDB\DBAdapter
    {
        return $this->adapter;
    }

    /**
     * Returns the underlying PDO instance if available.
     *
     * @return \PDO|null
     */
    public function getPDO(): ?\PDO
    {
        if (isset($this->adapter) && method_exists($this->adapter, 'getPDO')) {
            return $this->adapter->getPDO();
        }
        return null;
    }

    /**
     * Returns a human-readable summary of the current connection.
     */
    public function getConnectionSummary(): string
    {
        return "Database (" . strtoupper($this->dbtype ?? 'PDO') . ": " . ($this->dbname ?? 'unknown') . ")";
    }

    /**
     * Proxy unknown method calls to the underlying adapter.
     */
    public function __call($name, $arguments)
    {
        error_log("SPPDB::__call: " . $name);
        if (method_exists($this->adapter, $name)) {
            return call_user_func_array([$this->adapter, $name], $arguments);
        }
    }

    /** @var \SPPMod\SPPInterDB\DBAdapter|null Lazy-loaded read replica adapter */
    private ?\SPPMod\SPPInterDB\DBAdapter $readAdapter = null;
    private bool $forcePrimary = false;

    /**
     * Intelligently routes the query to the read replica or primary DB.
     */
    private function getAdapterForQuery(string $sql): \SPPMod\SPPInterDB\DBAdapter
    {
        if ($this->forcePrimary || preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)/i', $sql)) {
            $this->forcePrimary = true; // Stick to primary for rest of request to avoid replication lag
            return $this->adapter;
        }
        
        if ($this->readAdapter) {
            return $this->readAdapter;
        }
        
        // Attempt to connect to replica lazily if configured
        $context = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
        $settings = self::loadGlobalSettings();
        $replicas = $settings['apps'][$context]['db_replicas'] ?? \SPP\Module::getConfig('db_replicas', 'sppdb');
        
        if (!empty($replicas) && is_array($replicas)) {
            $replica = $replicas[array_rand($replicas)];
            try {
                $dbtype = $replica['dbtype'] ?? $this->dbtype;
                $pdo = new \PDO("{$dbtype}:host={$replica['dbhost']};dbname={$this->dbname}", $replica['dbuser'] ?? null, $replica['dbpasswd'] ?? null);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $this->readAdapter = new \SPPMod\SPPInterDB\PDOAdapter($pdo);
                return $this->readAdapter;
            } catch (\Exception $e) {
                error_log("SPPDB Replica connection failed, falling back to primary: " . $e->getMessage());
            }
        }
        
        return $this->adapter;
    }

    public function prepare(string $query, array $options = [])
    {
        $targetAdapter = $this->getAdapterForQuery($query);
        if ($targetAdapter instanceof \SPP\Core\PDOAdapter) {
            return $targetAdapter->query($query, $options); // Simplified for proxy
        }
        return null;
    }

    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        return $this->getAdapterForQuery($query)->query($query);
    }

    public function exec(string $statement)
    {
        return $this->getAdapterForQuery($statement)->execute($statement);
    }

    public function execute_query($sql, $values = [])
    {
        $result = $this->getAdapterForQuery($sql)->query($sql, (array)$values);
        $this->numrows = count($result);
        return $result;
    }

    public function exec_squery($sql, $tabname, $values = [])
    {
        $qry = $this->build_query($sql, $tabname);
        return $this->execute_query($qry, $values);
    }

    public function exec_squery_cursor($sql, $tabname, $values = []): \Generator
    {
        $qry = $this->build_query($sql, $tabname);
        $adapter = $this->getAdapterForQuery($qry);
        if (method_exists($adapter, 'cursor')) {
            return $adapter->cursor($qry, (array)$values);
        }

        // Fallback for adapters without cursor support
        $result = $adapter->query($qry, (array)$values);
        foreach ($result as $row) {
            yield $row;
        }
    }

    private function build_query($sql, $tabname)
    {
        $result = $sql;
        if (is_array($tabname)) {
            foreach ($tabname as $tab) {
                $result = \SPP\SPPUtils::str_replace_count('%tab%', $tab, $result, 1);
            }
        } else {
            $result = \SPP\SPPUtils::str_replace_count('%tab%', $tabname, $result, 1);
        }
        return $result;
    }

    public function tableExists($table)
    {
        return $this->adapter->tableExists($table);
    }

    public function columnExists($table, $col)
    {
        try {
            $schema = $this->adapter->getSchema($table);
            return isset($schema['columns'][$col]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function insertValues(string $table, array $columns, array $values = [])
    {
        $data = [];
        if (empty($values)) {
            $data = $columns;
        } else {
            foreach ($columns as $i => $col) {
                $data[$col] = $values[$i] ?? null;
            }
        }
        return $this->adapter->insert($table, $data);
    }

    public function insertMany(string $table, array $records)
    {
        if (empty($records)) {
            return 0;
        }

        $columns = array_keys(reset($records));
        $safeCols = [];
        foreach ($columns as $col) {
            $safeCols[] = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        }
        $colsStr = implode(', ', $safeCols);
        
        $placeholders = [];
        $values = [];
        
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        
        foreach ($records as $record) {
            $placeholders[] = $rowPlaceholder;
            foreach ($columns as $col) {
                $values[] = $record[$col] ?? null;
            }
        }
        
        $sql = "INSERT INTO {$table} ({$colsStr}) VALUES " . implode(', ', $placeholders);
        $this->getAdapterForQuery($sql)->execute($sql, $values);
        return count($records);
    }

    public function updateMany(string $table, array $records, string $index)
    {
        if (empty($records)) {
            return 0;
        }

        $columns = array_keys(reset($records));
        $columns = array_filter($columns, function($col) use ($index) { return $col !== $index; });

        if (empty($columns)) {
            return 0;
        }

        $values = [];
        $cases = [];
        $ids = [];

        foreach ($columns as $col) {
            $cases[$col] = "{$col} = CASE {$index}";
        }

        foreach ($records as $record) {
            $id = $record[$index];
            $ids[] = $id;

            foreach ($columns as $col) {
                $cases[$col] .= " WHEN ? THEN ?";
                $values[] = $id;
                $values[] = $record[$col];
            }
        }

        $whereIn = implode(',', array_fill(0, count($ids), '?'));
        
        foreach ($columns as $col) {
            $cases[$col] .= " END";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $cases) . " WHERE {$index} IN ({$whereIn})";
        
        foreach ($ids as $id) {
            $values[] = $id;
        }

        $this->getAdapterForQuery($sql)->execute($sql, $values);
        return count($records);
    }

    public function updateValues(string $table, array $columns, string $where, array $values = [])
    {
        $data = [];
        $is_assoc = array_keys($columns) !== range(0, count($columns) - 1);
        if ($is_assoc) {
            $data = $columns;
        } else {
            // This is a bit complex for the old API, but we try to map it
            // Assuming $values contains [col1_val, col2_val, ..., where_val1, where_val2]
            foreach ($columns as $i => $col) {
                $data[$col] = array_shift($values);
            }
        }
        return $this->adapter->update($table, $data, $where, $values);
    }

    public function add_columns($table, $cols = [])
    {
        // This is engine specific (DDL), so we pass to execute
        foreach ($cols as $col => $type) {
            if ($this->columnExists($table, $col)) {
                continue;
            }

            try {
                $this->exec("ALTER TABLE {$table} ADD {$col} {$type}");
            } catch (\Exception $e) {
                $message = $e->getMessage();
                if (stripos($message, 'Duplicate column') !== false || stripos($message, 'already exists') !== false) {
                    continue;
                }
                throw $e;
            }
        }
    }

    public function createTableIncremental(string $tableName, array $columns)
    {
        if (!$this->tableExists($tableName)) {
            // Basic DDL proxy
            $this->adapter->execute("CREATE TABLE {$tableName} (placeholder INT)");
        }
        $this->add_columns($tableName, $columns);
    }

    /**
     * public function safeInsert(string $tableName, array $data, string $identityField)
     *
     * Inserts a record only if the identity field value is not already present.
     */
    public function safeInsert(string $tableName, array $data, string $identityField)
    {
        if (!isset($data[$identityField])) {
            throw new \SPP\SPPException("Identity field '{$identityField}' missing in seed data.");
        }

        $tableNameSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $identityFieldSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $identityField);

        $checkSql = "SELECT count(*) as cnt FROM {$tableNameSafe} WHERE {$identityFieldSafe} = ?";
        $res = $this->execute_query($checkSql, [$data[$identityField]]);

        if ((int)$res[0]['cnt'] === 0) {
            $this->insertValues($tableName, $data);
            return true;
        }
        return false;
    }

    public function beginTransaction(): bool
    {
        return $this->adapter->beginTransaction();
    }
    public function commit(): bool
    {
        return $this->adapter->commit();
    }
    public function rollBack(): bool
    {
        return $this->adapter->rollBack();
    }

    /**
     * Executes a Closure within a database transaction.
     */
    public function transaction(\Closure $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
//\SPP\Module::endWS();
