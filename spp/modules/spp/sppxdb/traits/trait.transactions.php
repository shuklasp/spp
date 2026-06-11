<?php

namespace SPPMod\SPPXDB;

trait XDB_Transactions
{
    /**
     * Begins a transaction.
     */
    public function beginTransaction()
    {
        $this->inTransaction = true;
        return $this;
    }

    /**
     * Commits the current transaction.
     */
    public function commit()
    {
        if (!$this->inTransaction) {
            return false;
        }
        $this->inTransaction = false;
        return $this->save();
    }

    /**
     * Rolls back the current transaction.
     */
    public function rollback()
    {
        if (!$this->inTransaction) {
            return false;
        }

        // Invalidate cache
        unset($this->queryCache[$this->dbName][$this->tableName]);

        // Reload the pristine document from disk
        $this->doc = new \DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        if (file_exists($this->filePath)) {
            $this->doc->load($this->filePath);
        } else {
            $this->doc->loadXML("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<database name=\"{$this->dbName}\" table=\"{$this->tableName}\"><_schema></_schema><data></data></database>");
        }
        $this->xpath = new \DOMXPath($this->doc);

        $this->inTransaction = false;
        return true;
    }

    public function beginGlobalTransaction()
    {
        if ($this->globalTransactionActive) {
            return false;
        }
        $this->globalTransactionActive = true;
        $this->globalTransactionId = uniqid('gtx_');
        $this->journal = [];
        return $this->globalTransactionId;
    }

    public function commitGlobal()
    {
        if (!$this->globalTransactionActive) {
            return false;
        }

        // Finalize all journaled tables
        foreach ($this->journal as $tableName => $doc) {
            $tableXdb = new self($this->dbName, $tableName);
            if ($doc instanceof \DOMDocument) {
                $tableXdb->doc = $doc;
                $tableXdb->xpath = new \DOMXPath($doc);
            }
            $tableXdb->save();
        }

        $this->globalTransactionActive = false;
        $this->globalTransactionId = null;
        $this->journal = [];
        return true;
    }

    public function rollbackGlobal()
    {
        if (!$this->globalTransactionActive) {
            return false;
        }

        // Invalidate all changes (they were only in memory/temp segments)
        $this->globalTransactionActive = false;
        $this->globalTransactionId = null;
        $this->journal = [];
        return true;
    }

}
