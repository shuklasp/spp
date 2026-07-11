<?php
namespace SPPMod\SPPOS;

/**
 * Class VirtualPDOStatement
 * 
 * Intercepts execute() calls on Prepared Statements to ensure that
 * bound parameters containing secrets are encrypted before they hit the database,
 * and to push the mutated data into the XDB Master Sink (CDC).
 */
class VirtualPDOStatement extends \PDOStatement
{
    /** @var \PDO */
    protected $pdo;

    protected function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function execute(?array $params = null): bool
    {
        // 1. If params are provided, intercept them for secrets
        if ($params !== null) {
            foreach ($params as $key => $value) {
                if (is_string($value) && (strpos($value, 'sk_test_') === 0 || strpos($value, 'sk_live_') === 0)) {
                    // Encrypt vault secrets
                    $params[$key] = \SPPMod\SPPCrypto\Vault::encryptSecret($value);
                }
            }
        }

        // 2. Dispatch the execution to the CDC Queue (XDB Sink)
        // Since we don't have the fully assembled query string easily available,
        // we pass the raw SQL with the parameter payload for indexing.
        $queryString = $this->queryString;
        
        if (stripos(trim($queryString), 'INSERT') === 0 || stripos(trim($queryString), 'UPDATE') === 0) {
            $this->dispatchToQueue($queryString, $params ?? []);
        }

        // 3. Execute the native query securely
        return parent::execute($params);
    }

    private function dispatchToQueue(string $query, array $params)
    {
        if (class_exists('\SPPMod\SPPStorage\XdbBinaryIndexer')) {
            // Reconstruct a pseudo-query or pass the params directly to the indexer
            $pseudoQuery = $query . " | PARAMS: " . json_encode($params);
            \SPPMod\SPPStorage\XdbBinaryIndexer::indexQueryData($pseudoQuery);
        }
    }
}
