<?php

namespace SPPMod\SPPXDB\Engines;

use DOMDocument;
use DOMXPath;
use Exception;

require_once dirname(__DIR__) . '/traits/trait.core.php';
require_once dirname(__DIR__) . '/traits/trait.indexing.php';
require_once dirname(__DIR__) . '/traits/trait.views.php';
require_once dirname(__DIR__) . '/traits/trait.crud.php';
require_once dirname(__DIR__) . '/traits/trait.schema.php';
require_once dirname(__DIR__) . '/traits/trait.misc.php';
require_once dirname(__DIR__) . '/traits/trait.acl.php';
require_once dirname(__DIR__) . '/traits/trait.locking.php';
require_once dirname(__DIR__) . '/traits/trait.transactions.php';
require_once dirname(__DIR__) . '/traits/trait.encryption.php';
require_once dirname(__DIR__) . '/traits/trait.query.php';
require_once dirname(__DIR__) . '/traits/trait.raft.php';
require_once dirname(__DIR__) . '/traits/trait.sqlparser.php';
require_once dirname(__DIR__) . '/traits/trait.validator.php';

// Next-Gen Native XML Database Subsystems
require_once dirname(__DIR__) . '/Tree/NodeRecord.php';
require_once dirname(__DIR__) . '/Tree/XdbTreeEngine.php';
require_once dirname(__DIR__) . '/Index/StructuralPathIndex.php';
require_once dirname(__DIR__) . '/Index/ValueBTreeIndex.php';
require_once dirname(__DIR__) . '/Index/FullTextIndex.php';
require_once dirname(__DIR__) . '/Query/XQueryEngine.php';
require_once dirname(__DIR__) . '/Mutation/XQUFEngine.php';
require_once dirname(__DIR__) . '/Concurrency/MVCCManager.php';
require_once dirname(__DIR__) . '/Interoperability/JsonXmlBridge.php';

// Apex XML Database Subsystems
require_once dirname(__DIR__) . '/Storage/XbamEngine.php';
require_once dirname(__DIR__) . '/Index/UniversalIndex.php';
require_once dirname(__DIR__) . '/AI/SemanticChunk.php';
require_once dirname(__DIR__) . '/AI/SemanticChunker.php';
require_once dirname(__DIR__) . '/AI/RagEngine.php';
require_once dirname(__DIR__) . '/Temporal/BiTemporalEngine.php';
require_once dirname(__DIR__) . '/Temporal/MerkleLedger.php';
require_once dirname(__DIR__) . '/Reactive/LiveSubscription.php';
require_once dirname(__DIR__) . '/Reactive/LiveQueryManager.php';

class XMLEngine
{
    protected $baseDataDir;
    protected $dataDir;
    protected $dbName;
    protected $tableName;
    protected $filePath;
    protected $doc;
    protected $xpath;
    protected $lastResultSet = null;
    protected $lockHandle = null;
    protected $inTransaction = false;
    protected $transactionDoc = null;
    protected $lastInsertId = null;
    protected $queryCache = [];
    protected $indexes = [];
    protected $encryptedFields = [];
    protected $encryptionKey = 'spp-secret-key';
    protected $hooks = [];
    protected $auditingEnabled = false;
    protected $segments = [];
    protected $currentSegment = 0;
    protected $maxRowsPerSegment = 5000;
    protected $permissions = [];
    protected $isSaving = false;
    protected $foreignKeys = [];
    protected $views = [];
    protected $remoteNodes = [];
    protected $globalTransactionActive = false;
    protected $globalTransactionId = null;
    protected $journal = [];
    protected $nodeState = 'FOLLOWER';
    protected $currentTerm = 0;
    protected $votedFor = null;

    protected ?\SPPMod\SPPXDB\Tree\XdbTreeEngine $treeEngine = null;
    protected ?\SPPMod\SPPXDB\Concurrency\MVCCManager $mvccManager = null;
    protected ?\SPPMod\SPPXDB\Index\StructuralPathIndex $structuralIndex = null;
    protected ?\SPPMod\SPPXDB\Index\ValueBTreeIndex $valueBTreeIndex = null;
    protected ?\SPPMod\SPPXDB\Index\FullTextIndex $fullTextIndex = null;
    protected ?\SPPMod\SPPXDB\Storage\XbamEngine $xbamEngine = null;
    protected ?\SPPMod\SPPXDB\Index\UniversalIndex $universalIndex = null;
    protected ?\SPPMod\SPPXDB\AI\RagEngine $ragEngine = null;
    protected ?\SPPMod\SPPXDB\Temporal\BiTemporalEngine $biTemporalEngine = null;
    protected ?\SPPMod\SPPXDB\Temporal\MerkleLedger $merkleLedger = null;
    protected ?\SPPMod\SPPXDB\Reactive\LiveQueryManager $liveQueryManager = null;
    protected array $savepoints = [];
    protected array $virtualTables = [];

    use \SPPMod\SPPXDB\XDB_Core;
    use \SPPMod\SPPXDB\XDB_Indexing;
    use \SPPMod\SPPXDB\XDB_Views;
    use \SPPMod\SPPXDB\XDB_Crud;
    use \SPPMod\SPPXDB\XDB_Schema;
    use \SPPMod\SPPXDB\XDB_Misc;
    use \SPPMod\SPPXDB\XDB_Acl;
    use \SPPMod\SPPXDB\XDB_Locking;
    use \SPPMod\SPPXDB\XDB_Transactions;
    use \SPPMod\SPPXDB\XDB_Encryption;
    use \SPPMod\SPPXDB\XDB_Query;
    use \SPPMod\SPPXDB\XDB_Raft;
    use \SPPMod\SPPXDB\XDB_Sqlparser;
    use \SPPMod\SPPXDB\XDB_Validator;

    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Retrieves the Pre/Post/Level Native Tree Engine.
     */
    public function getTreeEngine(): \SPPMod\SPPXDB\Tree\XdbTreeEngine
    {
        if ($this->treeEngine === null) {
            $this->treeEngine = new \SPPMod\SPPXDB\Tree\XdbTreeEngine(
                $this->dataDir,
                'default',
                $this->tableName ?: 'main'
            );
            if ($this->filePath && file_exists($this->filePath)) {
                $this->treeEngine->ingestXml(file_get_contents($this->filePath));
            } elseif ($this->doc) {
                $this->treeEngine->ingestXml($this->doc->saveXML());
            }
        }
        return $this->treeEngine;
    }

    /**
     * Retrieves the MVCC Transaction Manager.
     */
    public function getMvccManager(): \SPPMod\SPPXDB\Concurrency\MVCCManager
    {
        if ($this->mvccManager === null) {
            $this->mvccManager = new \SPPMod\SPPXDB\Concurrency\MVCCManager($this->dataDir ?: dirname(__DIR__) . '/data/default');
        }
        return $this->mvccManager;
    }

    /**
     * Retrieves the Structural Path Index.
     */
    public function getStructuralIndex(): \SPPMod\SPPXDB\Index\StructuralPathIndex
    {
        if ($this->structuralIndex === null) {
            $this->structuralIndex = new \SPPMod\SPPXDB\Index\StructuralPathIndex();
            $nodes = $this->getTreeEngine()->getNodes();
            if (!empty($nodes)) {
                $this->structuralIndex->buildFromNodes($nodes);
            }
        }
        return $this->structuralIndex;
    }

    /**
     * Retrieves the Value B-Tree Index.
     */
    public function getValueBTreeIndex(): \SPPMod\SPPXDB\Index\ValueBTreeIndex
    {
        if ($this->valueBTreeIndex === null) {
            $this->valueBTreeIndex = new \SPPMod\SPPXDB\Index\ValueBTreeIndex();
        }
        return $this->valueBTreeIndex;
    }

    /**
     * Retrieves the Full-Text Index.
     */
    public function getFullTextIndex(): \SPPMod\SPPXDB\Index\FullTextIndex
    {
        if ($this->fullTextIndex === null) {
            $this->fullTextIndex = new \SPPMod\SPPXDB\Index\FullTextIndex();
            $nodes = $this->getTreeEngine()->getNodes();
            if (!empty($nodes)) {
                $this->fullTextIndex->buildFromNodes($nodes);
            }
        }
        return $this->fullTextIndex;
    }

    /**
     * Executes a native W3C XQuery 3.1 / XPath 3.1 expression.
     */
    public function queryXQuery(string $xquery, array $vars = []): array
    {
        $engine = new \SPPMod\SPPXDB\Query\XQueryEngine($this->getTreeEngine(), $this->dataDir);
        return $engine->query($xquery, $vars);
    }

    /**
     * Executes an in-place XQuery Update Facility (XQUF) mutation.
     */
    public function updateXQUF(string $xquf): bool
    {
        $tree = $this->getTreeEngine();
        $mvcc = $this->getMvccManager();
        $targetDoc = $this->filePath ?: ($this->dataDir . '/' . $this->tableName . '.xml');
        $undoData = file_exists($targetDoc) ? file_get_contents($targetDoc) : null;

        $xqufEngine = new \SPPMod\SPPXDB\Mutation\XQUFEngine($tree);
        $res = $xqufEngine->execute($xquf);

        if ($res && $mvcc->inTransaction()) {
            $redoData = $tree->exportXml();
            $mvcc->logMutation($targetDoc, 'XQUF', $undoData, $redoData);
        }

        // Keep DOM in sync if active and persist to disk
        if ($res) {
            $updatedXml = $tree->exportXml();
            if ($targetDoc) {
                @file_put_contents($targetDoc, $updatedXml);
            }
            if ($this->doc) {
                @$this->doc->loadXML($updatedXml);
                $this->xpath = new DOMXPath($this->doc);
            }
        }
        return $res;
    }

    /**
     * Queries arbitrary JSON using XPath/XQuery syntax.
     */
    public function queryJson($json, string $xquery): array
    {
        return \SPPMod\SPPXDB\Interoperability\JsonXmlBridge::queryJson($json, $xquery);
    }

    /**
     * Retrieves or initializes the VectorIndex for this table.
     */
    public function getVectorIndex(?int $dimension = null): \SPPMod\SPPXDB\Index\VectorIndex
    {
        require_once dirname(__DIR__) . '/Index/VectorIndex.php';
        $indexPath = $this->dataDir . '/_indexes/' . $this->tableName . '/_vector.json';
        $vIndex = new \SPPMod\SPPXDB\Index\VectorIndex($dimension);
        if (file_exists($indexPath)) {
            $vIndex->load($indexPath);
        }
        return $vIndex;
    }

    /**
     * Executes a holistic TwigStack join over the current table.
     */
    public function twigJoin(string $rootTag, array $branches = [], bool $isChildOnly = false): array
    {
        $engine = new \SPPMod\SPPXDB\Query\XQueryEngine($this->getTreeEngine(), $this->dataDir);
        return $engine->twigJoin($rootTag, $branches, $isChildOnly);
    }

    /**
     * Creates an in-transaction savepoint snapshot.
     */
    public function createSavepoint(string $name): bool
    {
        $this->savepoints[$name] = [
            'docXml' => $this->doc ? $this->doc->saveXML() : null,
            'lastInsertId' => $this->lastInsertId,
            'tableName' => $this->tableName,
            'filePath' => $this->filePath
        ];
        return true;
    }

    /**
     * Rolls back database state to a designated savepoint.
     */
    public function rollbackToSavepoint(string $name): bool
    {
        if (!isset($this->savepoints[$name])) {
            return false;
        }
        $sp = $this->savepoints[$name];
        if ($sp['docXml']) {
            $this->doc = new \DOMDocument('1.0', 'UTF-8');
            @$this->doc->loadXML($sp['docXml']);
            $this->xpath = new \DOMXPath($this->doc);
            if ($sp['filePath'] && file_exists($sp['filePath'])) {
                @file_put_contents($sp['filePath'], $sp['docXml']);
            }
        }
        $this->lastInsertId = $sp['lastInsertId'];
        $this->treeEngine = null;
        return true;
    }

    /**
     * Releases and destroys a savepoint.
     */
    public function releaseSavepoint(string $name): bool
    {
        if (isset($this->savepoints[$name])) {
            unset($this->savepoints[$name]);
            return true;
        }
        return false;
    }

    /**
     * Sets an in-memory virtual table (e.g. for CTEs).
     */
    public function setVirtualTable(string $name, array $rows): void
    {
        $this->virtualTables[$name] = $rows;
    }

    /**
     * Checks if an in-memory virtual table exists.
     */
    public function hasVirtualTable(string $name): bool
    {
        return array_key_exists($name, $this->virtualTables);
    }

    /**
     * Retrieves an in-memory virtual table by name.
     */
    public function getVirtualTable(string $name): ?array
    {
        return $this->virtualTables[$name] ?? null;
    }

    /**
     * Removes an in-memory virtual table.
     */
    public function removeVirtualTable(string $name): void
    {
        unset($this->virtualTables[$name]);
    }

    /**
     * Clears all virtual tables.
     */
    public function clearVirtualTables(): void
    {
        $this->virtualTables = [];
    }

    /**
     * Retrieves the Zero-Copy Flat Buffer Engine (.xbam).
     */
    public function getXbamEngine(): \SPPMod\SPPXDB\Storage\XbamEngine
    {
        if ($this->xbamEngine === null) {
            $xbamPath = $this->dataDir . '/' . ($this->tableName ?: 'main') . '.xbam';
            $this->xbamEngine = new \SPPMod\SPPXDB\Storage\XbamEngine($xbamPath);
        }
        return $this->xbamEngine;
    }

    public function xbam(): \SPPMod\SPPXDB\Storage\XbamEngine
    {
        return $this->getXbamEngine();
    }

    /**
     * Retrieves the MarkLogic-style Universal Inverted Index.
     */
    public function getUniversalIndex(): \SPPMod\SPPXDB\Index\UniversalIndex
    {
        if ($this->universalIndex === null) {
            $this->universalIndex = new \SPPMod\SPPXDB\Index\UniversalIndex($this->dataDir ?: dirname(__DIR__) . '/data/default');
        }
        return $this->universalIndex;
    }

    public function universalIndex(): \SPPMod\SPPXDB\Index\UniversalIndex
    {
        return $this->getUniversalIndex();
    }

    /**
     * Retrieves the Native AI RAG Engine.
     */
    public function getRagEngine(?callable $embeddingGenerator = null): \SPPMod\SPPXDB\AI\RagEngine
    {
        if ($this->ragEngine === null) {
            $idx = $this->getVectorIndex();
            $this->ragEngine = new \SPPMod\SPPXDB\AI\RagEngine($idx, $embeddingGenerator);
        }
        return $this->ragEngine;
    }

    public function rag(?callable $embeddingGenerator = null): \SPPMod\SPPXDB\AI\RagEngine
    {
        return $this->getRagEngine($embeddingGenerator);
    }

    /**
     * Retrieves the Bi-Temporal Dual-Timeline Engine.
     */
    public function getBiTemporalEngine(): \SPPMod\SPPXDB\Temporal\BiTemporalEngine
    {
        if ($this->biTemporalEngine === null) {
            $this->biTemporalEngine = new \SPPMod\SPPXDB\Temporal\BiTemporalEngine($this->dataDir ?: dirname(__DIR__) . '/data/default');
        }
        return $this->biTemporalEngine;
    }

    public function temporal(): \SPPMod\SPPXDB\Temporal\BiTemporalEngine
    {
        return $this->getBiTemporalEngine();
    }

    /**
     * Retrieves the Cryptographic Merkle DAG Ledger.
     */
    public function getMerkleLedger(): \SPPMod\SPPXDB\Temporal\MerkleLedger
    {
        if ($this->merkleLedger === null) {
            $this->merkleLedger = new \SPPMod\SPPXDB\Temporal\MerkleLedger(
                $this->dataDir ?: dirname(__DIR__) . '/data/default',
                'xdb_' . ($this->tableName ?: 'merkle_ledger')
            );
        }
        return $this->merkleLedger;
    }

    public function merkle(): \SPPMod\SPPXDB\Temporal\MerkleLedger
    {
        return $this->getMerkleLedger();
    }

    /**
     * Retrieves the Reactive Live Query Manager (CDC & Turbo Streams).
     */
    public function getLiveQueryManager(): \SPPMod\SPPXDB\Reactive\LiveQueryManager
    {
        if ($this->liveQueryManager === null) {
            $this->liveQueryManager = new \SPPMod\SPPXDB\Reactive\LiveQueryManager();
        }
        return $this->liveQueryManager;
    }

    public function live(): \SPPMod\SPPXDB\Reactive\LiveQueryManager
    {
        return $this->getLiveQueryManager();
    }

    /**
     * Subscribes to live mutations on this table.
     */
    public function subscribeLive($criteria, string $targetDomId, string $action = 'replace', ?string $template = null): \SPPMod\SPPXDB\Reactive\LiveSubscription
    {
        return $this->getLiveQueryManager()->subscribe(
            $this->tableName ?: 'default',
            $criteria,
            $targetDomId,
            $action,
            $template
        );
    }
}
