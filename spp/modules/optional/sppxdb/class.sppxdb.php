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
    protected $maxRowsPerSegment = 5000;

    public function __set($name, $value)
    {
        $this->$name = $value;
        if ($this->adapter && property_exists($this->adapter, $name)) {
            $this->adapter->$name = $value;
        }
    }

    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
        return $this->adapter->$name ?? null;
    }

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

    public function table($table)
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Executes a native W3C XQuery 3.1 / XPath 3.1 expression.
     */
    public function queryXQuery(string $xquery, array $vars = []): array
    {
        return method_exists($this->adapter, 'queryXQuery') ? $this->adapter->queryXQuery($xquery, $vars) : [];
    }

    /**
     * Executes an in-place XQuery Update Facility (XQUF) mutation.
     */
    public function updateXQUF(string $xquf): bool
    {
        return method_exists($this->adapter, 'updateXQUF') ? $this->adapter->updateXQUF($xquf) : false;
    }

    /**
     * Retrieves the Pre/Post/Level Native Tree Engine.
     */
    public function getTreeEngine(): ?\SPPMod\SPPXDB\Tree\XdbTreeEngine
    {
        return method_exists($this->adapter, 'getTreeEngine') ? $this->adapter->getTreeEngine() : null;
    }

    /**
     * Retrieves the MVCC Transaction Manager.
     */
    public function getMvccManager(): ?\SPPMod\SPPXDB\Concurrency\MVCCManager
    {
        return method_exists($this->adapter, 'getMvccManager') ? $this->adapter->getMvccManager() : null;
    }

    /**
     * Queries arbitrary JSON using XPath/XQuery syntax.
     */
    public function queryJson($json, string $xquery): array
    {
        return method_exists($this->adapter, 'queryJson') ? $this->adapter->queryJson($json, $xquery) : [];
    }

    /**
     * Retrieves or initializes the VectorIndex for this table.
     */
    public function getVectorIndex(?int $dimension = null): ?\SPPMod\SPPXDB\Index\VectorIndex
    {
        return method_exists($this->adapter, 'getVectorIndex') ? $this->adapter->getVectorIndex($dimension) : null;
    }

    /**
     * Executes a holistic TwigStack join over the current table.
     */
    public function twigJoin(string $rootTag, array $branches = [], bool $isChildOnly = false): array
    {
        return method_exists($this->adapter, 'twigJoin') ? $this->adapter->twigJoin($rootTag, $branches, $isChildOnly) : [];
    }

    /**
     * Zero-Copy Flat Buffer Engine (.xbam) accessor.
     */
    public function xbam(): ?\SPPMod\SPPXDB\Storage\XbamEngine
    {
        return method_exists($this->adapter, 'xbam') ? $this->adapter->xbam() : null;
    }

    /**
     * MarkLogic-style Universal Inverted Index accessor.
     */
    public function universalIndex(): ?\SPPMod\SPPXDB\Index\UniversalIndex
    {
        return method_exists($this->adapter, 'universalIndex') ? $this->adapter->universalIndex() : null;
    }

    /**
     * Native AI RAG Subsystem accessor.
     */
    public function rag(?callable $embeddingGenerator = null): ?\SPPMod\SPPXDB\AI\RagEngine
    {
        return method_exists($this->adapter, 'rag') ? $this->adapter->rag($embeddingGenerator) : null;
    }

    /**
     * SQL:2011 / XTDB-style Bi-Temporal Time Travel Engine accessor.
     */
    public function temporal(): ?\SPPMod\SPPXDB\Temporal\BiTemporalEngine
    {
        return method_exists($this->adapter, 'temporal') ? $this->adapter->temporal() : null;
    }

    /**
     * Cryptographic Merkle DAG Ledger accessor.
     */
    public function merkle(): ?\SPPMod\SPPXDB\Temporal\MerkleLedger
    {
        return method_exists($this->adapter, 'merkle') ? $this->adapter->merkle() : null;
    }

    /**
     * Reactive Live Query Manager accessor (Hotwire Turbo Streams & SSE).
     */
    public function live(): ?\SPPMod\SPPXDB\Reactive\LiveQueryManager
    {
        return method_exists($this->adapter, 'live') ? $this->adapter->live() : null;
    }

    /**
     * Subscribes to live reactive mutations.
     */
    public function subscribeLive($criteria, string $targetDomId, string $action = 'replace', ?string $template = null): ?\SPPMod\SPPXDB\Reactive\LiveSubscription
    {
        return method_exists($this->adapter, 'subscribeLive') ? $this->adapter->subscribeLive($criteria, $targetDomId, $action, $template) : null;
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

        // Intercept QueryBuilder fluent methods
        $qbMethods = ['where', 'whereIn', 'whereLike', 'orderBy', 'limit', 'offset', 'get', 'first', 'count', 'select', 'distinct', 'with', 'withTrashed', 'onlyTrashed', 'asObject', 'whereXPath', 'whereXQuery', 'xquery', 'xquf'];
        if (in_array($name, $qbMethods)) {
            $qb = new QueryBuilder($this, $tableName);
            return call_user_func_array([$qb, $name], $args);
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
