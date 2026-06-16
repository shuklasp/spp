<?php

namespace SPPMod\SPPXDB;

trait XDB_Crud
{
    public function truncateTable($tableName)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $nodes = $this->xpath->query("//row");
            foreach ($nodes as $node) {
                $node->parentNode->removeChild($node);
            }
            $dir = $this->dataDir . '/_indexes/' . $this->tableName;
            if (is_dir($dir)) {
                foreach (glob($dir . '/*.json') as $file) {
                    file_put_contents($file, json_encode([]));
                }
            }
            $this->indexes = [];
            return $this->save();
        }
        return false;
    }

    public function renameTable($oldName, $newName)
    {
        $this->resolveTablePath($oldName);
        $oldPath = $this->dataDir . '/' . $this->tableName . '.xml';

        $newDb = $this->dbName;
        $newTable = $newName;
        if (strpos($newName, '.') !== false) {
            list($newDb, $newTable) = explode('.', $newName, 2);
        }
        $newPath = $this->baseDataDir . '/' . $newDb . '/' . $newTable . '.xml';

        if (file_exists($oldPath)) {
            // Rename XML
            @rename($oldPath, $newPath);
            // Rename segments
            foreach (glob($this->dataDir . '/' . $this->tableName . '.*.xml') as $seg) {
                $newSeg = str_replace($this->tableName . '.', $newTable . '.', basename($seg));
                @rename($seg, $this->baseDataDir . '/' . $newDb . '/' . $newSeg);
            }
            // Rename indexes
            $oldIdxDir = $this->dataDir . '/_indexes/' . $this->tableName;
            $newIdxDir = $this->baseDataDir . '/' . $newDb . '/_indexes/' . $newTable;
            if (is_dir($oldIdxDir)) {
                if (!is_dir(dirname($newIdxDir))) {
                    mkdir(dirname($newIdxDir), 0777, true);
                }
                @rename($oldIdxDir, $newIdxDir);
            }
            return true;
        }
        return false;
    }

    /**
     * Creates a table with an optional schema definition.
     * The schema is stored as metadata in the XML root element.
     *
     * @param string $table
     * @param array $columns ['col_name' => 'type', ...]
     * @return $this
     */
    public function createTable_OLD($table, $columns = [])
    {
        $this->tableName = $table;
        $this->filePath = $this->dataDir . '/' . $table . '.xml';

        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;
        $root = $this->doc->createElement('database');
        $root->setAttribute('table', $table);

        if (!empty($columns)) {
            $schema = $this->doc->createElement('_schema');
            foreach ($columns as $name => $type) {
                $col = $this->doc->createElement('column');
                $col->setAttribute('name', $name);
                $col->setAttribute('type', $type);
                $schema->appendChild($col);
            }
            $root->appendChild($schema);
        }

        $this->doc->appendChild($root);
        $this->save();
        $this->xpath = new DOMXPath($this->doc);

        $this->loadViews();
        $this->loadForeignKeys();

        return $this;
    }

    /**
     * Creates a new table (XML file) with an optional schema.
     *
     * @param string $tableName
     * @param array $columns ['name' => 'type', ...]
     * @return bool
     */
    public function createTable($tableName, $columns = [])
    {
        $path = $this->dataDir . '/' . $tableName . '.xml';
        if (file_exists($path)) {
            return false;
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<database name=\"{$this->dbName}\" table=\"{$tableName}\">\n";
        $xml .= "  <_schema>\n";
        foreach ($columns as $name => $props) {
            if ($name === '_constraints' && is_array($props)) {
                foreach ($props as $c) {
                    $ctype = $c['type'] ?? 'unique';
                    $ccols = is_array($c['columns']) ? implode(',', $c['columns']) : $c['columns'];
                    $xml .= "    <constraint type=\"{$ctype}\" columns=\"{$ccols}\" />\n";
                }
                continue;
            }

            if (is_string($props)) {
                $xml .= "    <column name=\"{$name}\" type=\"{$props}\" />\n";
            } else {
                $type = $props['type'] ?? 'text';
                $attrs = "name=\"{$name}\" type=\"{$type}\"";
                if (!empty($props['notNull'])) {
                    $attrs .= " notNull=\"true\"";
                }
                if (!empty($props['unique'])) {
                    $attrs .= " unique=\"true\"";
                }
                if (!empty($props['primary'])) {
                    $attrs .= " primary=\"true\"";
                }
                if (!empty($props['check'])) {
                    $attrs .= " check=\"" . htmlspecialchars($props['check']) . "\"";
                }
                if (isset($props['default'])) {
                    $attrs .= " default=\"" . htmlspecialchars($props['default']) . "\"";
                }
                $xml .= "    <column {$attrs} />\n";

                // If it's a foreign key
                if (!empty($props['references'])) {
                    $refParts = explode('.', $props['references']);
                    if (count($refParts) === 2) {
                        $this->addForeignKey($tableName, $name, $refParts[0], $refParts[1]);
                    }
                }
            }
        }
        $xml .= "  </_schema>\n";
        $xml .= "  <data>\n  </data>\n";
        $xml .= "</database>";

        return file_put_contents($path, $xml) !== false;
    }

    /**
     * Deletes a table and its associated segments and indexes.
     *
     * @param string $tableName
     * @return bool
     */
    public function dropTable($tableName)
    {
        $path = $this->dataDir . '/' . $tableName . '.xml';
        if (file_exists($path)) {
            unlink($path);
            // Drop segments
            foreach (glob($this->dataDir . '/' . $tableName . '.*.xml') as $seg) {
                unlink($seg);
            }
            // Drop indexes
            $idxDir = $this->dataDir . '/_indexes/' . $tableName;
            if (is_dir($idxDir)) {
                foreach (glob($idxDir . '/*') as $f) {
                    unlink($f);
                }
                rmdir($idxDir);
            }
            return true;
        }
        return false;
    }

    protected function nodeToArray($node)
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return [];
        }
        $row = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $val = $child->nodeValue;
                if (in_array($child->nodeName, $this->encryptedFields)) {
                    $val = $this->decrypt($val);
                }
                $row[$child->nodeName] = $val;
            }
        }
        // Include attributes
        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $row['@' . $attr->nodeName] = $attr->nodeValue;
                if ($attr->nodeName === 'id' && !isset($row['id'])) {
                    $row['id'] = $attr->nodeValue;
                }
            }
        }
        return $row;
    }

    /**
     * Updates records in the current table.
     *
     * @param array $data Associative array of field => value to update.
     * @param string|null $where SQL WHERE clause.
     * @param array $params Query parameters.
     * @return bool
     */
    public function update($data, $where = null, $params = [])
    {
        $this->validateData($data, false);
        $this->validateJsonSchema($data);
        $this->fireHook('beforeUpdate', $data);

        if (method_exists($this, 'fireObserverEvent')) {
            if ($this->fireObserverEvent('updating', $data) === false) {
                return false;
            }
        }

        $triggerData = ['table' => $this->tableName, 'data' => $data, 'where' => $where, 'params' => $params];
        if (class_exists('\\SPP\\SPPEvent')) {
            $evtTriggerData = new \SPP\EventParams($triggerData);
            \SPP\SPPEvent::fireEvent('xdb.before_update', $evtTriggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.before_update", $evtTriggerData);
            $data = $evtTriggerData->getPayload()['data'];
        }
        $xpath = "//row";
        if ($where) {
            $xpath .= "[" . $this->translateWhereToXPath($where, $params) . "]";
        }

        $nodes = $this->xpath->query($xpath);
        if ($nodes) {
            foreach ($nodes as $node) {
                // Temporal Data: Archive current state to <history>
                $history = $this->doc->createElement('history');
                $history->setAttribute('timestamp', date('Y-m-d H:i:s'));
                foreach ($node->childNodes as $child) {
                    if ($child->nodeName !== 'history') {
                        $history->appendChild($child->cloneNode(true));
                    }
                }
                $node->appendChild($history);

                $rowId = $node->getAttribute('id');
                // Old data for index update
                $oldData = [];
                foreach ($node->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        $oldData[$child->nodeName] = $child->nodeValue;
                    }
                }

                // Schema Validation
                $schema = $this->getSchema();

                $rowComputed = [];
                foreach ($data as $key => $value) {
                    $valToSave = $value;

                    // Evaluate arithmetic expression
                    if (is_string($value) && preg_match('/^([a-zA-Z0-9_]+)\s*([\+\-\*\/])\s*([a-zA-Z0-9_\.\'\"]+)$/', trim($value), $expMatch)) {
                        $colName = $expMatch[1];
                        $op = $expMatch[2];
                        $operandStr = trim($expMatch[3], "'\" ");

                        $currentVal = 0;
                        $fieldNode = $this->xpath->query($colName, $node)->item(0);
                        if ($fieldNode) {
                            $currentVal = (float)$fieldNode->nodeValue;
                        }

                        if (is_numeric($operandStr)) {
                            $operand = (float)$operandStr;
                        } else {
                            $opNode = $this->xpath->query($operandStr, $node)->item(0);
                            $operand = $opNode ? (float)$opNode->nodeValue : 0;
                        }

                        switch ($op) {
                            case '+': $valToSave = $currentVal + $operand;
                                break;
                            case '-': $valToSave = $currentVal - $operand;
                                break;
                            case '*': $valToSave = $currentVal * $operand;
                                break;
                            case '/': $valToSave = $operand != 0 ? $currentVal / $operand : 0;
                                break;
                        }
                    }

                    // Apply type coercion if schema exists
                    if (isset($schema['columns'][$key])) {
                        $stype = strtolower($schema['columns'][$key]['type']);
                        if ($stype === 'int') {
                            $valToSave = (int)$valToSave;
                        } elseif ($stype === 'float' || $stype === 'double') {
                            $valToSave = (float)$valToSave;
                        } elseif ($stype === 'bool' || $stype === 'boolean') {
                            $valToSave = $valToSave ? 'true' : 'false';
                        }
                    }

                    if (in_array($key, $this->encryptedFields)) {
                        $valToSave = $this->encrypt($valToSave);
                    }

                    $rowComputed[$key] = $valToSave;

                    $field = $this->xpath->query($key, $node)->item(0);
                    if ($field) {
                        $field->nodeValue = htmlspecialchars((string)$valToSave);
                    } else {
                        $newField = $this->doc->createElement($key);
                        $newField->appendChild($this->doc->createTextNode((string)$valToSave));
                        $node->appendChild($newField);
                    }
                }

                // Update indexes
                $this->updateIndexes($rowId, $oldData, 'remove');
                $updatedData = array_merge($oldData, $rowComputed);
                $this->updateIndexes($rowId, $updatedData, 'add');
            }
        }
        $result = $this->save();
        $this->logAudit('update', $data, $where);
        $this->fireHook('afterUpdate', $data);

        if (class_exists('\\SPP\\SPPEvent')) {
            $triggerData = ['table' => $this->tableName, 'data' => $data, 'count' => $nodes->length ?? 0];
            $evtTriggerData = new \SPP\EventParams($triggerData);
            \SPP\SPPEvent::fireEvent('xdb.after_update', $evtTriggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.after_update", $evtTriggerData);
        }

        if (method_exists($this, 'fireObserverEvent')) {
            $this->fireObserverEvent('updated', $data);
        }

        return $result;
    }

    /**
     * Deletes records from the current table.
     *
     * @param string|null $where SQL WHERE clause.
     * @param array $params Query parameters.
     * @return bool
     */
    public function delete($where = null, $params = [])
    {
        $this->fireHook('beforeDelete', $where);

        if (method_exists($this, 'fireObserverEvent')) {
            $obsData = ['where' => $where, 'params' => $params];
            if ($this->fireObserverEvent('deleting', $obsData) === false) {
                return false;
            }
        }

        $triggerData = ['table' => $this->tableName, 'where' => $where, 'params' => $params];
        if (class_exists('\\SPP\\SPPEvent')) {
            $evtTriggerData = new \SPP\EventParams($triggerData);
            \SPP\SPPEvent::fireEvent('xdb.before_delete', $evtTriggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.before_delete", $evtTriggerData);
            $where = $evtTriggerData->getPayload()['where'];
            $params = $evtTriggerData->getPayload()['params'];
        }
        $xpath = "//row";
        if ($where) {
            $xpath .= "[" . $this->translateWhereToXPath($where, $params) . "]";
        }

        $nodes = $this->xpath->query($xpath);
        if ($nodes && $nodes->length > 0) {
            $toRemove = [];
            foreach ($nodes as $node) {
                $toRemove[] = $node;
                $rowId = $node->getAttribute('id');

                // Foreign Key Cascading
                $this->handleCascadingDelete($rowId);

                // Remove from index
                $rowData = $this->nodeToArray($node);
                $this->updateIndexes($rowId, $rowData, 'remove');
            }
            foreach ($toRemove as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        $result = $this->save();
        $this->logAudit('delete', null, $where);
        $this->fireHook('afterDelete', $where);

        if (class_exists('\\SPP\\SPPEvent')) {
            $triggerData = ['table' => $this->tableName, 'where' => $where, 'count' => $nodes->length ?? 0];
            $evtTriggerData = new \SPP\EventParams($triggerData);
            \SPP\SPPEvent::fireEvent('xdb.after_delete', $evtTriggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.after_delete", $evtTriggerData);
        }

        if (method_exists($this, 'fireObserverEvent')) {
            $obsData = ['where' => $where, 'params' => $params];
            $this->fireObserverEvent('deleted', $obsData);
        }

        return $result;
    }

    /**
     * Inserts a record into the current table.
     * Supports auto-incrementing 'id' if not provided.
     *
     * @param array $data
     * @return bool
     */
    public function insert($data)
    {
        $root = $this->doc->documentElement;

        // Handle auto-increment ID
        if (!isset($data['id'])) {
            $meta = $this->xpath->query('/database/_meta/next_id')->item(0);
            if (!$meta) {
                // Ensure meta exists if it doesn't
                $metaNode = $this->doc->createElement('_meta');
                $meta = $this->doc->createElement('next_id', '1');
                $metaNode->appendChild($meta);
                $root->insertBefore($metaNode, $root->firstChild);
            }
            $id = $meta->nodeValue;
            $data['id'] = $id;
            $meta->nodeValue = $id + 1;
        }

        $this->validateData($data, true);
        $this->validateJsonSchema($data);
        $this->fireHook('beforeInsert', $data);

        if (method_exists($this, 'fireObserverEvent')) {
            if ($this->fireObserverEvent('creating', $data) === false) {
                return false;
            }
        }

        $triggerData = ['table' => $this->tableName, 'data' => $data];
        if (class_exists('\\SPP\\SPPEvent')) {
            $evtTriggerData = new \SPP\EventParams($triggerData);
            \SPP\SPPEvent::fireEvent('xdb.before_insert', $evtTriggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.before_insert", $evtTriggerData);
            $data = $evtTriggerData->getPayload()['data'];
        }

        $this->lastInsertId = $data['id'];

        $row = $this->doc->createElement('row');
        $row->setAttribute('id', $data['id']);

        $schema = $this->getSchema();

        // Ensure all schema fields exist, applying defaults if needed
        foreach ($schema['columns'] as $name => $props) {
            if (!isset($data[$name]) && $props['default'] !== null) {
                $data[$name] = $props['default'];
            }
        }

        foreach ($data as $key => $value) {
            $valToSave = $value;
            // Apply type coercion if schema exists
            if (isset($schema['columns'][$key])) {
                $stype = strtolower($schema['columns'][$key]['type']);
                if ($stype === 'int') {
                    $valToSave = (int)$value;
                } elseif ($stype === 'float' || $stype === 'double') {
                    $valToSave = (float)$value;
                } elseif ($stype === 'bool' || $stype === 'boolean') {
                    $valToSave = $value ? 'true' : 'false';
                }
            }

            if (in_array($key, $this->encryptedFields)) {
                $valToSave = $this->encrypt($valToSave);
            }

            $field = $this->doc->createElement($key);
            $field->appendChild($this->doc->createTextNode((string)$valToSave));
            $row->appendChild($field);
        }

        $root->appendChild($row);

        // Update indexes
        $this->updateIndexes($data['id'], $data, 'add');

        $result = $this->save();
        $this->logAudit('insert', $data);
        $this->fireHook('afterInsert', $data);

        if (class_exists('\\SPP\\SPPEvent')) {
            $triggerData = ['table' => $this->tableName, 'data' => $data, 'id' => $this->lastInsertId];
            $evtTriggerData = new \SPP\EventParams($triggerData);
            \SPP\SPPEvent::fireEvent('xdb.after_insert', $evtTriggerData);
            \SPP\SPPEvent::fireEvent("xdb.{$this->tableName}.after_insert", $evtTriggerData);
        }

        if (method_exists($this, 'fireObserverEvent')) {
            $this->fireObserverEvent('created', $data);
        }

        return $result;
    }

    /**
     * Saves the current DOM to file with atomic write and locking.
     *
     * @return bool
     */
    public function save()
    {
        if ($this->isSaving) {
            return true;
        }
        if (!$this->filePath) {
            return false;
        }
        $this->checkAccess('write');

        if ($this->globalTransactionActive) {
            $this->journal[$this->tableName] = $this->doc;
            return true; // Defer to global commit
        }

        $this->isSaving = true;

        // Invalidate cache
        unset($this->queryCache[$this->dbName][$this->tableName]);

        if ($this->inTransaction) {
            $this->isSaving = false;
            return true;
        }

        $this->lock(LOCK_EX);

        $tempFile = $this->filePath . '.tmp';
        $result = $this->doc->save($tempFile);

        if ($result !== false) {
            if ($this->lockHandle) {
                ftruncate($this->lockHandle, 0);
                rewind($this->lockHandle);
                $result = fwrite($this->lockHandle, file_get_contents($tempFile)) !== false;
                fflush($this->lockHandle);
                @unlink($tempFile);
            } else {
                if (file_exists($this->filePath)) {
                    @unlink($this->filePath);
                }
                $result = @rename($tempFile, $this->filePath);
                if (!$result) {
                    error_log("SPPXDB::save - FAILED TO RENAME temp file '{$tempFile}' to '{$this->filePath}'");
                }
            }
        } else {
            error_log("SPPXDB::save - DOMDocument::save FAILED for '{$tempFile}'");
        }

        $this->unlock();

        // Horizontal Partitioning: Check if current segment is full
        if ($result !== false) {
            $rowCount = $this->xpath->query("//row")->length;
            if ($rowCount >= $this->maxRowsPerSegment) {
                $segmentCount = count($this->segments);
                $newSegmentPath = str_replace('.xml', '.' . $segmentCount . '.xml', $this->filePath);
                @rename($this->filePath, $newSegmentPath);
                $this->segments[] = $newSegmentPath;
                // Re-initialize current table as empty
                $this->createTable($this->tableName);
            }
        }

        $this->isSaving = false;
        if ($result !== false) {
            $this->logBinlog($this->tableName, 'mutation', ['file' => $this->filePath]);
        }
        return $result !== false;
    }

}
