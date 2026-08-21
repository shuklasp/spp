<?php
namespace SPPMod\SPPReport\Services;

class SnapshotService
{
    private string $snapshotDir;

    public function __construct()
    {
        $this->snapshotDir = (defined('APP_ROOT') ? APP_ROOT . '/var/snapshots' : __DIR__ . '/../../var/snapshots');
        if (!is_dir($this->snapshotDir)) {
            @mkdir($this->snapshotDir, 0755, true);
        }
    }

    public function createSnapshot(string $reportName, \Generator $dataStream): bool
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reportName);
        $idxFile = $this->snapshotDir . '/' . $safeName . '.xdb';
        
        if (class_exists('\\SPPMod\\SPPStorage\\XdbBinaryIndexer')) {
            $indexer = new \SPPMod\SPPStorage\XdbBinaryIndexer($idxFile);
            $indexer->beginIndex();
            foreach ($dataStream as $row) {
                $indexer->appendRecord($row);
            }
            $indexer->commit();
            return true;
        }

        // Fallback if XDB is not available
        $data = iterator_to_array($dataStream);
        file_put_contents($idxFile, serialize($data));
        return true;
    }

    public function hasValidSnapshot(string $reportName): bool
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reportName);
        $idxFile = $this->snapshotDir . '/' . $safeName . '.xdb';
        return file_exists($idxFile);
    }

    public function streamSnapshot(string $reportName): \Generator
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $reportName);
        $idxFile = $this->snapshotDir . '/' . $safeName . '.xdb';

        if (!file_exists($idxFile)) {
            return;
        }

        if (class_exists('\\SPPMod\\SPPStorage\\XdbBinaryIndexer')) {
            $indexer = new \SPPMod\SPPStorage\XdbBinaryIndexer($idxFile);
            foreach ($indexer->streamRecords() as $record) {
                yield $record;
            }
            return;
        }

        // Fallback
        $data = unserialize(file_get_contents($idxFile));
        foreach ($data as $record) {
            yield $record;
        }
    }
}
