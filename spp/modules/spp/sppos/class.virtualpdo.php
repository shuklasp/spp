<?php
namespace SPPMod\SPPOS;

/**
 * Class VirtualPDO
 * 
 * The Hardware Abstraction Layer for the WebOS.
 * Intercepts PDO queries from guest apps. Fast-paths SELECTs, and intercepts
 * Writes (INSERT/UPDATE) to trigger the asynchronous CDC DAG Queue.
 */
class VirtualPDO extends \PDO
{
    private $isolationLevel;
    private $prefix;
    
    public function __construct($dsn, $username = null, $password = null, $options = null, $isolationLevel = 'virtual', $prefix = '')
    {
        // Intercept spp://kernel connection strings
        if (strpos($dsn, 'spp://kernel') === 0) {
            // Reroute to actual physical SPP DB or Dedicated DB based on isolation
            $this->isolationLevel = $isolationLevel;
            $this->prefix = $prefix;
            
            if ($isolationLevel === 'virtual') {
                $dsn = 'mysql:host=localhost;dbname=spp_master';
            } else {
                // Physical isolation
                $dsn = 'mysql:host=localhost;dbname=spp_guest_' . trim($prefix, '_');
            }
        }

        parent::__construct($dsn, $username, $password, $options);
    }

    public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs): \PDOStatement|false
    {
        if (stripos(trim($query), 'SELECT') === 0) {
            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        }
        $this->guardAndDispatch($query);
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        if (stripos(trim($statement), 'SELECT') !== 0) {
            $this->guardAndDispatch($statement);
        }
        return parent::exec($statement);
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if (stripos(trim($query), 'SELECT') !== 0) {
            if (!KernelGuard::canWrite(KernelGuard::getCurrentAppId(), $query)) {
                throw new \PDOException("WebOS Security Exception: Application lacks write permissions for this operation.");
            }
        }

        // Force PDO to return our intercepted VirtualPDOStatement
        if (!isset($options[\PDO::ATTR_STATEMENT_CLASS])) {
            $options[\PDO::ATTR_STATEMENT_CLASS] = ['SPPMod\SPPOS\VirtualPDOStatement', [$this]];
        }

        return parent::prepare($query, $options);
    }

    private function guardAndDispatch(string &$query)
    {
        // 2. KERNEL GUARD: Check IAM permissions for writes
        if (!KernelGuard::canWrite(KernelGuard::getCurrentAppId(), $query)) {
            throw new \PDOException("WebOS Security Exception: Application lacks write permissions for this operation.");
        }

        // 3. INTERCEPT: Vault Secret masking (Don't save known secrets in plain text)
        $query = $this->interceptSecrets($query);

        // 4. ASYNCHRONOUS CDC & XDB SINK: Dispatch the intercepted write to DAG and XDB
        $this->dispatchToQueue($query);
    }
    
    private function interceptSecrets(string $query): string
    {
        // Use the robust SQL AST Lexer instead of regex
        return SqlLexer::interceptStringAssignments($query, function($column, $value) {
            // Check if the value looks like a secret (starts with sk_test_ etc)
            if (strpos($value, 'sk_test_') === 0 || strpos($value, 'sk_live_') === 0) {
                return \SPPMod\SPPCrypto\Vault::encryptSecret($value);
            }
            return $value; // Return unchanged if not a secret
        });
    }

    private function dispatchToQueue(string $query)
    {
        // Fire-and-forget to the Redis/Memcached DAG Job Queue.
        // \SPPMod\SPPQueue\DagJobOrchestrator::dispatch(new \SPPMod\SPPIntegrations\IntegrationSyncJob(['query' => $query]));
        
        // Push the CDC data stream directly into the SPP XDB Master Index Sink
        if (class_exists('\SPPMod\SPPStorage\XdbBinaryIndexer')) {
            \SPPMod\SPPStorage\XdbBinaryIndexer::indexQueryData($query);
        }
    }
}
