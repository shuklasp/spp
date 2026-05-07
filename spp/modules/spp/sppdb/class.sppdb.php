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
        error_log("SPPDB DEBUG: Resolved table '$tname' to '$finalTable' for context '$context' with prefix '$prefix'");
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
        if ($cached !== null) return $cached;

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
     * Creates or reuses a database connection, supporting per-app overrides.
     */
    public function __construct($dburl = null, $dbuser = null, $dbpasswd = null, $options = null, bool $shared = true)
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
                    $dbhost = $dbOverride['dbhost'] ?? \SPP\Module::getConfig('dbhost', 'sppdb');
                    $dbname = $dbOverride['dbname'] ?? \SPP\Module::getConfig('dbname', 'sppdb');
                    $url = $dbtype . ':host=' . $dbhost . ';dbname=' . $dbname;
                    $dbuser = $dbOverride['dbuser'] ?? \SPP\Module::getConfig('dbuser', 'sppdb');
                    $dbpasswd = $dbOverride['dbpasswd'] ?? \SPP\Module::getConfig('dbpasswd', 'sppdb');
                } else {
                    $dbtype = \SPP\Module::getConfig('dbtype', 'sppdb');
                    $dbhost = \SPP\Module::getConfig('dbhost', 'sppdb');
                    $dbname = \SPP\Module::getConfig('dbname', 'sppdb');
                    $url = ($dbtype && $dbhost && $dbname) ? ($dbtype . ':host=' . $dbhost . ';dbname=' . $dbname) : null;
                    $dbuser = \SPP\Module::getConfig('dbuser', 'sppdb');
                    $dbpasswd = \SPP\Module::getConfig('dbpasswd', 'sppdb');
                }
            } else {
                $url = $dburl;
                $dbuser = ($dbuser == null) ? \SPP\Module::getConfig('dbuser', 'sppdb') : $dbuser;
                $dbpasswd = ($dbpasswd == null) ? \SPP\Module::getConfig('dbpasswd', 'sppdb') : $dbpasswd;
                
                if (preg_match('/^([a-z0-9]+):/', $url, $m)) {
                    $this->dbtype = $m[1];
                }
            }
            
            $this->dbname = $dbname ?: 'default';

            // -- Adapter Initialization --
            if ($this->dbtype === 'xdb') {
                $xdbFile = dirname(__DIR__) . '/sppxdb/class.sppxdb.php';
                if (file_exists($xdbFile)) require_once $xdbFile;
                
                $xdb = new \SPPMod\SPPXDB\SPP_XDB($this->dbname ?: 'default');
                $this->adapter = new \SPPMod\SPPInterDB\XDBAdapter($xdb);
                return;
            }

            // Diagnostic validation for PDO
            if (!$url || !$dbuser) {
                $configPath = \SPP\Module::getExpectedConfigPath('sppdb');
                $missing = [];
                if (!isset($dbhost)) $missing[] = 'dbhost';
                if (!isset($dbname)) $missing[] = 'dbname';
                if (!$dbuser) $missing[] = 'dbuser';
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
                if ($dbuser == null && $dbpasswd == null && $options == null) {
                    $pdo = new \PDO($url);
                } elseif ($options == null) {
                    $pdo = new \PDO($url, $dbuser, $dbpasswd);
                } else {
                    $pdo = new \PDO($url, $dbuser, $dbpasswd, $options);
                }
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                if ($shared && $key) {
                    self::$sharedConnections[$key] = $pdo;
                }
            }

            $this->adapter = new \SPPMod\SPPInterDB\PDOAdapter($pdo);

        } catch (\Exception $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new \SPP\SPPException("Database Connection Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
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
        if (!isset($groups[$groupName])) return [];
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
        return call_user_func_array([$this->adapter, $name], $arguments);
    }

    public function prepare(string $query, array $options = [])
    {
        if ($this->adapter instanceof \SPP\Core\PDOAdapter) {
            return $this->adapter->query($query, $options); // Simplified for proxy
        }
        return null;
    }

    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs)
    {
        return $this->adapter->query($query);
    }

    public function exec(string $statement)
    {
        return $this->adapter->execute($statement);
    }

    public function execute_query($sql, $values = array())
    {
        $result = $this->adapter->query($sql, (array)$values);
        $this->numrows = count($result);
        return $result;
    }

    public function exec_squery($sql, $tabname, $values = array())
    {
        $qry = $this->build_query($sql, $tabname);
        return $this->execute_query($qry, $values);
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

    public function insertValues(string $table, array $columns, array $values = array())
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

    public function updateValues(string $table, array $columns, string $where, array $values = array())
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

    public function add_columns($table, $cols = array())
    {
        // This is engine specific (DDL), so we pass to execute
        foreach ($cols as $col => $type) {
            $this->exec("ALTER TABLE {$table} ADD {$col} {$type}");
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

    public function beginTransaction(): bool { return $this->adapter->beginTransaction(); }
    public function commit(): bool { return $this->adapter->commit(); }
    public function rollBack(): bool { return $this->adapter->rollBack(); }
}
//\SPP\Module::endWS();
?>
