<?php

namespace SPPMod\SPPXDB;

trait XDB_Schema
{
    public function dropColumn($tableName, $colName)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $schemaNode = $this->xpath->query('/database/_schema')->item(0);
            if ($schemaNode) {
                $colNode = $this->xpath->query("column[@name='{$colName}']", $schemaNode)->item(0);
                if ($colNode) {
                    $schemaNode->removeChild($colNode);

                    $rows = $this->xpath->query("//row/{$colName}");
                    foreach ($rows as $row) {
                        $row->parentNode->removeChild($row);
                    }

                    $this->dropIndex($colName);
                    return $this->save();
                }
            }
        }
        return false;
    }

    public function renameColumn($tableName, $oldCol, $newCol)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $schemaNode = $this->xpath->query('/database/_schema')->item(0);
            if ($schemaNode) {
                $colNode = $this->xpath->query("column[@name='{$oldCol}']", $schemaNode)->item(0);
                if ($colNode) {
                    $colNode->setAttribute('name', $newCol);

                    $rows = $this->xpath->query("//row/{$oldCol}");
                    foreach ($rows as $row) {
                        $newEl = $this->doc->createElement($newCol);
                        while ($row->firstChild) {
                            $newEl->appendChild($row->firstChild);
                        }
                        $row->parentNode->replaceChild($newEl, $row);
                    }

                    $oldIdx = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $oldCol . '.json';
                    $newIdx = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $newCol . '.json';
                    if (file_exists($oldIdx)) {
                        @rename($oldIdx, $newIdx);
                    }
                    return $this->save();
                }
            }
        }
        return false;
    }

    public function modifyColumn($tableName, $colName, $colProps)
    {
        $this->resolveTablePath($tableName);
        if ($this->doc && $this->xpath) {
            $schemaNode = $this->xpath->query('/database/_schema')->item(0);
            if ($schemaNode) {
                $colNode = $this->xpath->query("column[@name='{$colName}']", $schemaNode)->item(0);
                if ($colNode) {
                    $props = ['type' => 'text'];
                    if (preg_match('/^([a-z]+)/i', $colProps, $pm)) {
                        $props['type'] = $pm[1];
                    }
                    if (stripos($colProps, 'NOT NULL') !== false) {
                        $props['notNull'] = true;
                    }
                    if (stripos($colProps, 'PRIMARY KEY') !== false) {
                        $props['primary'] = true;
                    }
                    if (stripos($colProps, 'AUTO_INCREMENT') !== false) {
                        $props['autoIncrement'] = true;
                    }
                    if (stripos($colProps, 'UNIQUE') !== false) {
                        $props['unique'] = true;
                    }

                    foreach (['type', 'notNull', 'primary', 'autoIncrement', 'unique'] as $attr) {
                        $colNode->removeAttribute($attr);
                    }

                    foreach ($props as $k => $v) {
                        $colNode->setAttribute($k, $v === true ? 'true' : $v);
                    }
                    return $this->save();
                }
            }
        }
        return false;
    }

    protected function loadForeignKeys()
    {
        $fkPath = $this->dataDir . '/_fks.json';
        if (file_exists($fkPath)) {
            $this->foreignKeys = json_decode(file_get_contents($fkPath), true) ?: [];
        }
    }

    public function verifyIntegrity()
    {
        $report = ['status' => 'healthy', 'issues' => []];

        // Check segments
        foreach ($this->segments as $seg) {
            if (!file_exists($seg)) {
                $report['issues'][] = "Missing segment file: $seg";
                $report['status'] = 'degraded';
            } else {
                $doc = new DOMDocument();
                if (!@$doc->load($seg)) {
                    $report['issues'][] = "Corrupt XML in segment: $seg";
                    $report['status'] = 'danger';
                }
            }
        }

        // Check Foreign Keys (Orphans)
        foreach ($this->foreignKeys as $childTable => $fks) {
            foreach ($fks as $fk) {
                $refTable = $fk['refTable'];
                $refXdb = new self($this->dbName, $refTable);
                $childXdb = new self($this->dbName, $childTable);

                $childRows = $childXdb->queryX("//row");
                foreach ($childRows as $row) {
                    $val = $row[$fk['localCol']] ?? null;
                    if ($val && empty($refXdb->queryX("//row[@id='$val']"))) {
                        $report['issues'][] = "Orphaned reference in $childTable: {$fk['localCol']} = $val points to missing $refTable.";
                        $report['status'] = 'degraded';
                    }
                }
            }
        }

        return $report;
    }

    /**
     * Returns the schema of the current table (if defined).
     *
     * @return array ['col_name' => 'type', ...]
     */
    public function getSchema()
    {
        if (!$this->xpath) {
            return ['columns' => [], 'constraints' => []];
        }

        $colNodes = $this->xpath->query('/database/_schema/column');
        $conNodes = $this->xpath->query('/database/_schema/constraint');

        $schema = ['columns' => [], 'constraints' => []];

        if ($colNodes) {
            foreach ($colNodes as $col) {
                $name = $col->getAttribute('name');
                $schema['columns'][$name] = [
                    'type' => $col->getAttribute('type'),
                    'notNull' => $col->getAttribute('notNull') === 'true',
                    'unique' => $col->getAttribute('unique') === 'true',
                    'primary' => $col->getAttribute('primary') === 'true',
                    'check' => $col->hasAttribute('check') ? $col->getAttribute('check') : null,
                    'default' => $col->hasAttribute('default') ? $col->getAttribute('default') : null
                ];
            }
        }

        if ($conNodes) {
            foreach ($conNodes as $con) {
                $schema['constraints'][] = [
                    'type' => $con->getAttribute('type'),
                    'columns' => explode(',', $con->getAttribute('columns'))
                ];
            }
        }

        return $schema;
    }

    /**
     * Returns the column names for a specific table.
     *
     * @param string $tableName
     * @return array
     */
    public function getTableColumns($tableName)
    {
        $this->connect($tableName);
        $schema = $this->getSchema();
        if (!empty($schema['columns'])) {
            return array_keys($schema['columns']);
        }

        // Fallback: get keys from first row
        $nodes = $this->xpath->query("//row");
        if ($nodes && $nodes->length > 0) {
            $row = $this->nodeToArray($nodes->item(0));
            // Filter out internal fields like @id, history, etc.
            return array_filter(array_keys($row), function ($k) {
                return $k[0] !== '@' && $k !== 'history';
            });
        }
        return [];
    }

    /**
     * Lists all available databases in the XDB data directory.
     *
     * @return array
     */
    public function listDatabases()
    {
        $path = $this->baseDataDir;
        if (!is_dir($path)) {
            return [];
        }
        $dirs = array_filter(glob($path . '/*'), 'is_dir');
        $dbs = array_map('basename', $dirs);
        // Exclude system/internal dirs if any
        return array_values(array_filter($dbs, function ($d) {
            return $d[0] !== '_';
        }));
    }

    /**
     * Lists all tables in a specific database.
     *
     * @param string|null $dbName
     * @return array
     */
    public function listTables($dbName = null)
    {
        $dbName = $dbName ?: $this->dbName;
        $path = $this->baseDataDir . '/' . $dbName;
        if (!is_dir($path)) {
            return [];
        }
        $files = glob($path . '/*.xml');
        $tables = [];
        foreach ($files as $file) {
            $base = basename($file, '.xml');
            // Ignore segments like table.0.xml
            if (preg_match('/\.\d+$/', $base)) {
                continue;
            }
            // Ignore temporary files
            if (str_ends_with($file, '.tmp')) {
                continue;
            }

            $tables[] = $base;
        }
        return array_unique($tables);
    }

}
