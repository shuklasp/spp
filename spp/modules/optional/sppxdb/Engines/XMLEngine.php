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
}
