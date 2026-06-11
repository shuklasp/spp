<?php

namespace SPPMod\SPPXDB;

trait XDB_Query
{
    protected function trackQuery($sql)
    {
        $statsPath = $this->dataDir . '/_query_stats.json';
        $stats = file_exists($statsPath) ? json_decode(file_get_contents($statsPath), true) : [];

        // Normalize SQL (remove values)
        $normSql = preg_replace('/\'[^\']*\'|[0-9]+/', '?', $sql);
        if (!isset($stats[$normSql])) {
            $stats[$normSql] = 0;
        }
        $stats[$normSql]++;

        file_put_contents($statsPath, json_encode($stats, JSON_PRETTY_PRINT));

        // Simple suggestion logic
        if ($stats[$normSql] > 100) {
            // If many queries on same column, suggest index
            if (preg_match('/WHERE\s+([a-zA-Z0-9_]+)/i', $normSql, $m)) {
                $col = $m[1];
                if (!isset($this->indexes[$this->tableName][$col])) {
                    $this->logAudit('suggestion', "Consider indexing column '$col' in table '{$this->tableName}' for better performance.");
                }
            }
        }
    }

    /**
     * Executes an XPath query (XQuery-lite).
     *
     * @param string $xpathQuery
     * @return array
     */
    public function queryX($xpathQuery)
    {
        $this->checkAccess('read');

        // Multi-segment support
        $allResults = [];
        $originalDoc = $this->doc;
        $originalXpath = $this->xpath;
        $originalPath = $this->filePath;

        // Simple Index Utilization (O(1) lookup conversion)
        // If the query is just a simple equality match and the index exists
        if (preg_match('#^//row\[([a-zA-Z0-9_]+)\s*=\s*\'([^\']+)\'\]$#', trim($xpathQuery), $m)) {
            $col = $m[1];
            $val = $m[2];
            $idxPath = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $col . '.json';
            if (isset($this->indexes[$col]) || file_exists($idxPath)) {
                if (!isset($this->indexes[$col])) {
                    $this->loadIndex($col);
                }
                if (isset($this->indexes[$col][$val])) {
                    $ids = $this->indexes[$col][$val];
                    if (empty($ids)) return [];
                    
                    // Rewrite query to lookup by @id which is significantly faster and limits nodes
                    $idConds = [];
                    foreach ($ids as $id) {
                        $idConds[] = "@id='" . addslashes($id) . "'";
                    }
                    $xpathQuery = "//row[" . implode(' or ', $idConds) . "]";
                } else {
                    return []; // Index exists but value not found
                }
            }
        }

        $pathsToScan = $this->segments;
        if (!in_array($this->filePath, $pathsToScan) && file_exists($this->filePath)) {
            $pathsToScan[] = $this->filePath;
        }

        foreach ($pathsToScan as $segmentPath) {
            $this->filePath = $segmentPath;
            $doc = new \DOMDocument();

            // Transparent Compression support
            $loaded = false;
            if (substr($segmentPath, -3) === '.gz') {
                $content = gzdecode(file_get_contents($segmentPath));
                $loaded = @$doc->loadXML($content);
            } else {
                $loaded = @$doc->load($segmentPath);
            }

            if ($loaded) {
                $xpath = new \DOMXPath($doc);
                $old_error = libxml_use_internal_errors(true);
                $nodes = $xpath->query($xpathQuery);
                if ($nodes === false) {
                    $errors = libxml_get_errors();
                    $error_msg = empty($errors) ? "Unknown error" : $errors[0]->message;
                    error_log("Invalid XPath in queryX: " . $xpathQuery . " Error: " . $error_msg);
                    libxml_clear_errors();
                }
                libxml_use_internal_errors($old_error);
                if ($nodes) {
                    foreach ($nodes as $node) {
                        $allResults[] = $this->nodeToArray($node);
                    }
                }
            }
        }

        // Restore original connection
        $this->doc = $originalDoc;
        $this->xpath = $originalXpath;
        $this->filePath = $originalPath;

        $this->lastResultSet = $allResults;
        return $allResults;
    }

    /**
     * Memory-efficient streaming query for large tables.
     * Uses XMLReader to iterate over rows without loading the entire DOM.
     *
     * @param callable $callback function($rowData)
     * @return int Number of rows processed
     */
    public function streamQuery($callback)
    {
        if (!$this->filePath || !file_exists($this->filePath)) {
            return 0;
        }

        $reader = new \XMLReader();
        if (!$reader->open($this->filePath)) {
            return 0;
        }

        $count = 0;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
                $node = $reader->expand();
                $rowData = $this->nodeToArray($node);
                if ($callback($rowData) === false) {
                    break;
                }
                $count++;
            }
        }
        $reader->close();
        return $count;
    }

}
