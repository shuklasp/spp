<?php

namespace SPPMod\SPPWorkflow\Commands;

use SPP\CLI\Command;
use SPPMod\SPPWorkflow\CQRS\EventStore;

/**
 * GenerateSnapshotsCommand
 * Scans active CQRS event logs/tables, replays event streams, and generates point-in-time
 * state snapshots for workflow entities to optimize future state reconstitution.
 */
class GenerateSnapshotsCommand extends Command
{
    protected string $name = 'cqrs:snapshots:generate';
    protected string $description = 'Scan CQRS event streams and generate point-in-time snapshots for workflow entities';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "\033[32mINFO:\033[0m Scanning CQRS event store for snapshot candidates...\n";

        if (!class_exists('\SPPMod\SPPWorkflow\CQRS\EventStore')) {
            require_once dirname(__DIR__) . '/CQRS/EventStore.php';
        }

        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cqrs' : '/tmp/spp_cqrs';
        if (!is_dir($baseDir)) {
            echo "\033[33mWARN:\033[0m No CQRS event directory found at `{$baseDir}`. Nothing to snapshot.\n";
            return;
        }

        $files = glob($baseDir . '/events_*.jsonl');
        if (empty($files)) {
            echo "\033[33mWARN:\033[0m No event stream files found in `{$baseDir}`.\n";
            return;
        }

        $count = 0;
        foreach ($files as $file) {
            $base = basename($file, '.jsonl');
            $entityType = substr($base, 7); // strip 'events_'

            $entities = [];
            $handle = @fopen($file, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $record = json_decode($line, true);
                    if ($record && isset($record['entity_id'])) {
                        $eId = (string)$record['entity_id'];
                        if (!isset($entities[$eId])) {
                            $entities[$eId] = ['state' => [], 'events' => 0];
                        }
                        // Apply payload to state
                        if (isset($record['payload']) && is_array($record['payload'])) {
                            $entities[$eId]['state'] = array_merge($entities[$eId]['state'], $record['payload']);
                        }
                        $entities[$eId]['events']++;
                    }
                }
                fclose($handle);
            }

            foreach ($entities as $eId => $data) {
                // Snapshot if entity has more than 5 events
                if ($data['events'] >= 1) {
                    EventStore::saveSnapshot($entityType, $eId, $data['state'], $data['events'] - 1);
                    echo "Generated snapshot for `{$entityType}` (ID: {$eId}) at event index " . ($data['events'] - 1) . ".\n";
                    $count++;
                }
            }
        }

        echo "\033[32mSUCCESS:\033[0m Completed CQRS snapshot generation. Total snapshots created/updated: {$count}.\n";
    }
}
