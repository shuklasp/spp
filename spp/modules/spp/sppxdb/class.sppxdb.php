<?php

namespace SPPMod\SPPXDB;

require_once __DIR__ . '/class.querybuilder.php';
require_once __DIR__ . '/traits/trait.observer.php';
require_once __DIR__ . '/Engines/XMLEngine.php';
require_once __DIR__ . '/Engines/SQLiteEngine.php';

/**
 * class SPP_XDB
 *
 * Facade/Proxy for the XML and SQLite Database Engines.
 */
class SPP_XDB
{
    use XDB_Observer;

    protected $adapter;

    public static $queryLog = [];
    public static $logQueries = false;

    public static function enableQueryLog()
    {
        self::$logQueries = true;
        self::$queryLog = [];
    }

    public static function getQueryLog()
    {
        return self::$queryLog;
    }

    public function __construct($db = 'default', $table = null)
    {
        $engine = 'xml';
        if (class_exists('\\SPP\\SPPConfig')) {
            $engine = \SPP\SPPConfig::get('sys:db.engine', 'xml');
        }
        if ($engine === 'xml' && class_exists('\\SPP\\Module')) {
            $modEngine = \SPP\Module::getConfig('sys:db.engine', 'sppxdb');
            if ($modEngine) {
                $engine = $modEngine;
            }
        }

        if ($engine === 'sqlite') {
            $this->adapter = new Engines\SQLiteEngine($db, $table);
        } else {
            $this->adapter = new Engines\XMLEngine($db, $table);
        }
    }

    public function __call($name, $args)
    {
        $cacheEnabled = false;
        if (class_exists('\\SPP\\SPPConfig') && class_exists('\\SPP\\Cache')) {
            $cacheEnabled = \SPP\SPPConfig::get('sys:db.cache_enabled', true);
        }

        $cacheKey = null;
        $tableName = null;
        if (method_exists($this->adapter, 'getTableName')) {
            $tableName = $this->adapter->getTableName();
        }

        // Check if querySQL is actually a read query
        $isReadQuery = false;
        if ($name === 'querySQL') {
            $sql = trim($args[0] ?? '');
            if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESCRIBE') === 0) {
                $isReadQuery = true;
            }
        }

        // Cache read queries
        if ($cacheEnabled && $isReadQuery && $tableName) {
            $sql = $args[0] ?? '';
            $params = $args[1] ?? [];
            
            $cacheKey = 'xdb_q_' . md5($sql . serialize($params));
            $cached = \SPP\Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Execute Query / Method
        $start = 0;
        if (self::$logQueries && in_array($name, ['querySQL', 'insert', 'update', 'delete'])) {
            $start = microtime(true);
        }

        $result = call_user_func_array([$this->adapter, $name], $args);

        // Try to get tableName again in case it was resolved during execution
        if (!$tableName && method_exists($this->adapter, 'getTableName')) {
            $tableName = $this->adapter->getTableName();
        }

        if ($start > 0) {
            $time = round((microtime(true) - $start) * 1000, 2);
            self::$queryLog[] = [
                'method' => $name,
                'args' => $args,
                'time_ms' => $time
            ];
        }

        // Save to cache after read
        if ($cacheKey && $tableName) {
            \SPP\Cache::setWithTags($cacheKey, $result, ["xdb_table_$tableName"], 3600);
        }

        // Cache busting on mutations
        $isMutation = in_array($name, ['insert', 'update', 'delete', 'save', 'insertBatch', 'updateBatch']);
        if ($name === 'querySQL' && !$isReadQuery) {
            $isMutation = true;
        }

        if ($cacheEnabled && $isMutation && $tableName) {
            \SPP\Cache::invalidateTag("xdb_table_$tableName");
        }

        // If the engine returned itself (e.g. for fluent method chaining like connect()),
        // we must return the Facade ($this) to preserve the SPP_XDB type-hint expectations.
        if ($result === $this->adapter) {
            return $this;
        }
        
        return $result;
    }
}
