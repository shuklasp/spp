<?php

namespace SPPMod\SPPXDB;

trait XDB_Indexing
{
    public function dropIndex($column)
    {
        if (!$this->tableName) {
            return false;
        }
        if (class_exists('\SPPMod\SPPXDB\Index\XdbBinaryIndexer')) {
            \SPPMod\SPPXDB\Index\XdbBinaryIndexer::invalidateIndex($this->dataDir, $this->tableName, $column);
        }
        $file = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $column . '.json';
        if (file_exists($file)) {
            @unlink($file);
            unset($this->indexes[$column]);
            return true;
        }
        return false;
    }

    /**
     * Creates an index for a specific column.
     *
     * @param string $column
     * @return bool
     */
    public function createIndex($column)
    {
        if (!$this->tableName) {
            return false;
        }

        $results = $this->queryX("//row");
        $index = [];
        foreach ($results as $row) {
            if (isset($row[$column])) {
                $val = $row[$column];
                $id = $row['id'] ?? $row['@id'];
                if (!isset($index[$val])) {
                    $index[$val] = [];
                }
                $index[$val][] = $id;
            }
        }

        $this->saveIndex($column, $index);
        $this->indexes[$column] = $index;

        if (class_exists('\SPPMod\SPPXDB\Index\XdbBinaryIndexer')) {
            \SPPMod\SPPXDB\Index\XdbBinaryIndexer::buildIndex($this->dataDir, $this->tableName, $column, $results);
        }

        return true;
    }

    /**
     * Search using high-performance binary index.
     */
    public function searchBinaryIndex($column, $value): array
    {
        if (!$this->tableName || !class_exists('\SPPMod\SPPXDB\Index\XdbBinaryIndexer')) {
            return [];
        }
        return \SPPMod\SPPXDB\Index\XdbBinaryIndexer::searchIndex($this->dataDir, $this->tableName, $column, $value);
    }

    /**
     * Saves an index to disk.
     */
    protected function saveIndex($column, $data)
    {
        $dir = $this->dataDir . '/_indexes/' . $this->tableName;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir . '/' . $column . '.json', json_encode($data));
    }

    /**
     * Loads an index from disk.
     */
    protected function loadIndex($column)
    {
        $file = $this->dataDir . '/_indexes/' . $this->tableName . '/' . $column . '.json';
        if (file_exists($file)) {
            $this->indexes[$column] = json_decode(file_get_contents($file), true);
            return true;
        }
        return false;
    }

    /**
     * Updates all indexes for a specific row.
     */
    protected function updateIndexes($rowId, $data, $action = 'add')
    {
        $dir = $this->dataDir . '/_indexes/' . $this->tableName;
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*.json') as $file) {
            $column = pathinfo($file, PATHINFO_FILENAME);
            if (!isset($this->indexes[$column])) {
                $this->loadIndex($column);
            }

            $val = $data[$column] ?? null;
            if ($val !== null) {
                if ($action === 'add') {
                    if (!isset($this->indexes[$column][$val])) {
                        $this->indexes[$column][$val] = [];
                    }
                    if (!in_array($rowId, $this->indexes[$column][$val])) {
                        $this->indexes[$column][$val][] = $rowId;
                    }
                } else {
                    if (isset($this->indexes[$column][$val])) {
                        $key = array_search($rowId, $this->indexes[$column][$val]);
                        if ($key !== false) {
                            unset($this->indexes[$column][$val][$key]);
                            $this->indexes[$column][$val] = array_values($this->indexes[$column][$val]);
                            if (empty($this->indexes[$column][$val])) {
                                unset($this->indexes[$column][$val]);
                            }
                        }
                    }
                }
                $this->saveIndex($column, $this->indexes[$column]);
            }
        }
    }

}
