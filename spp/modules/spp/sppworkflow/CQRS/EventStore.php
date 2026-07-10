<?php

namespace SPPMod\SPPWorkflow\CQRS;

use SPP\SPPConfig;
use SPPMod\SPPDB\SPPDB;

/**
 * EventStore
 * Implements an Event Sourcing and CQRS foundation, supporting configurable storage backends.
 * Defaults to a high-performance append-only log format as per enterprise specification.
 */
class EventStore
{
    /**
     * Appends an immutable domain event to the event store.
     *
     * @param string $entityType The aggregate root entity type
     * @param string $entityId The aggregate root entity ID
     * @param string $eventType The domain event name (e.g. 'workflow.transitioned')
     * @param array $payload Event data payload
     * @param array $metadata Additional tracing/context metadata
     */
    public static function append(string $entityType, string $entityId, string $eventType, array $payload, array $metadata = []): void
    {
        $driver = SPPConfig::get('cqrs.event_store.driver', 'append_only_log');

        $eventRecord = [
            'event_id' => bin2hex(random_bytes(16)),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event_type' => $eventType,
            'payload' => $payload,
            'metadata' => array_merge($metadata, [
                'timestamp' => microtime(true),
                'date' => date('Y-m-d H:i:s.u'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'traceparent' => class_exists('\SPPMod\SPPAudit\Middleware\TraceContextMiddleware') ? \SPPMod\SPPAudit\Middleware\TraceContextMiddleware::getTraceparent() : null
            ])
        ];

        if ($driver === 'db') {
            self::appendToDatabase($eventRecord);
        } else {
            self::appendToLog($eventRecord);
        }
    }

    /**
     * Append to high-performance append-only flat-file log (default).
     */
    private static function appendToLog(array $eventRecord): void
    {
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cqrs' : '/tmp/spp_cqrs';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $cleanEntityType = preg_replace('/[^a-zA-Z0-9_-]/', '', $eventRecord['entity_type']);
        $filePath = $baseDir . "/events_{$cleanEntityType}.jsonl";

        $jsonLine = json_encode($eventRecord, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($filePath, $jsonLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Append to database event store table.
     */
    private static function appendToDatabase(array $eventRecord): void
    {
        try {
            if (!class_exists('\SPPMod\SPPDB\SPPDB')) {
                self::appendToLog($eventRecord);
                return;
            }

            $db = new SPPDB();
            $table = SPPDB::sppTable('spp_cqrs_events');

            if (!$db->tableExists($table)) {
                self::installDatabaseTable($db, $table);
            }

            $db->exec_squery(
                "INSERT INTO %tab% (event_id, entity_type, entity_id, event_type, payload, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
                $table,
                [
                    $eventRecord['event_id'],
                    $eventRecord['entity_type'],
                    (string)$eventRecord['entity_id'],
                    $eventRecord['event_type'],
                    json_encode($eventRecord['payload']),
                    json_encode($eventRecord['metadata']),
                    date('Y-m-d H:i:s')
                ]
            );
        } catch (\Exception $e) {
            error_log("EventStore Database Append Failed: " . $e->getMessage());
            // Fallback to append-only log to guarantee event immutability/durability
            self::appendToLog($eventRecord);
        }
    }

    /**
     * Installs the CQRS event store database table.
     */
    private static function installDatabaseTable(SPPDB $db, string $table): void
    {
        $driver = 'sqlite';
        try {
            $pdo = $db->getPDO();
            if ($pdo) {
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            }
        } catch (\Exception $e) {}

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id VARCHAR(64) NOT NULL UNIQUE,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(100) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                metadata TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(64) NOT NULL UNIQUE,
                entity_type VARCHAR(100) NOT NULL,
                entity_id VARCHAR(100) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                metadata TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_event (event_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $db->exec_squery($sql, $table);
    }

    /**
     * Retrieve the event stream for a specific aggregate root.
     *
     * @param string $entityType
     * @param string $entityId
     * @return array
     */
    public static function getEventStream(string $entityType, string $entityId): array
    {
        $driver = SPPConfig::get('cqrs.event_store.driver', 'append_only_log');

        if ($driver === 'db' && class_exists('\SPPMod\SPPDB\SPPDB')) {
            try {
                $db = new SPPDB();
                $table = SPPDB::sppTable('spp_cqrs_events');
                if ($db->tableExists($table)) {
                    $results = $db->exec_squery("SELECT * FROM %tab% WHERE entity_type = ? AND entity_id = ? ORDER BY id ASC", $table, [$entityType, (string)$entityId]);
                    $stream = [];
                    foreach ($results as $row) {
                        $stream[] = [
                            'event_id' => $row['event_id'],
                            'entity_type' => $row['entity_type'],
                            'entity_id' => $row['entity_id'],
                            'event_type' => $row['event_type'],
                            'payload' => json_decode($row['payload'], true),
                            'metadata' => json_decode($row['metadata'], true)
                        ];
                    }
                    return $stream;
                }
            } catch (\Exception $e) {
                error_log("Failed to load event stream from DB: " . $e->getMessage());
            }
        }

        // Fallback or append-only log retrieval
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cqrs' : '/tmp/spp_cqrs';
        $cleanEntityType = preg_replace('/[^a-zA-Z0-9_-]/', '', $entityType);
        $filePath = $baseDir . "/events_{$cleanEntityType}.jsonl";

        if (!file_exists($filePath)) {
            return [];
        }

        $stream = [];
        $handle = @fopen($filePath, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $record = json_decode($line, true);
                if ($record && isset($record['entity_id']) && (string)$record['entity_id'] === (string)$entityId) {
                    $stream[] = $record;
                }
            }
            fclose($handle);
        }

        return $stream;
    }

    /**
     * Saves a point-in-time snapshot of an aggregate root entity state.
     *
     * @param string $entityType
     * @param string $entityId
     * @param array $state The current reconstituted entity state
     * @param int $lastEventIndex The index/version of the last event applied
     */
    public static function saveSnapshot(string $entityType, string $entityId, array $state, int $lastEventIndex): void
    {
        $driver = SPPConfig::get('cqrs.event_store.driver', 'append_only_log');
        $snapshotData = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'state' => $state,
            'last_event_index' => $lastEventIndex,
            'created_at' => date('Y-m-d H:i:s.u'),
            'timestamp' => microtime(true)
        ];

        if ($driver === 'db' && class_exists('\SPPMod\SPPDB\SPPDB')) {
            try {
                $db = new SPPDB();
                $table = SPPDB::sppTable('spp_cqrs_snapshots');
                if (!$db->tableExists($table)) {
                    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        entity_type VARCHAR(100) NOT NULL,
                        entity_id VARCHAR(100) NOT NULL,
                        state TEXT NOT NULL,
                        last_event_index INT NOT NULL,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE(entity_type, entity_id)
                    )";
                    $db->exec_squery($sql, $table);
                }
                
                // Upsert snapshot
                $db->exec_squery("DELETE FROM %tab% WHERE entity_type = ? AND entity_id = ?", $table, [$entityType, (string)$entityId]);
                $db->exec_squery(
                    "INSERT INTO %tab% (entity_type, entity_id, state, last_event_index, created_at) VALUES (?, ?, ?, ?, ?)",
                    $table,
                    [$entityType, (string)$entityId, json_encode($state), $lastEventIndex, date('Y-m-d H:i:s')]
                );
                return;
            } catch (\Exception $e) {
                error_log("EventStore Database Snapshot Failed: " . $e->getMessage());
            }
        }

        // File-based snapshot storage
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cqrs/snapshots' : '/tmp/spp_cqrs/snapshots';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }

        $cleanEntityType = preg_replace('/[^a-zA-Z0-9_-]/', '', $entityType);
        $cleanEntityId = preg_replace('/[^a-zA-Z0-9_-]/', '', $entityId);
        $filePath = $baseDir . "/snapshot_{$cleanEntityType}_{$cleanEntityId}.json";

        file_put_contents($filePath, json_encode($snapshotData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Retrieves the latest snapshot for an aggregate root entity.
     *
     * @param string $entityType
     * @param string $entityId
     * @return array|null Returns ['state' => ..., 'last_event_index' => ...] or null
     */
    public static function getLatestSnapshot(string $entityType, string $entityId): ?array
    {
        $driver = SPPConfig::get('cqrs.event_store.driver', 'append_only_log');

        if ($driver === 'db' && class_exists('\SPPMod\SPPDB\SPPDB')) {
            try {
                $db = new SPPDB();
                $table = SPPDB::sppTable('spp_cqrs_snapshots');
                if ($db->tableExists($table)) {
                    $res = $db->exec_squery("SELECT state, last_event_index FROM %tab% WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1", $table, [$entityType, (string)$entityId]);
                    if (!empty($res) && isset($res[0])) {
                        return [
                            'state' => json_decode($res[0]['state'], true),
                            'last_event_index' => (int)$res[0]['last_event_index']
                        ];
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to load snapshot from DB: " . $e->getMessage());
            }
        }

        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/var/cqrs/snapshots' : '/tmp/spp_cqrs/snapshots';
        $cleanEntityType = preg_replace('/[^a-zA-Z0-9_-]/', '', $entityType);
        $cleanEntityId = preg_replace('/[^a-zA-Z0-9_-]/', '', $entityId);
        $filePath = $baseDir . "/snapshot_{$cleanEntityType}_{$cleanEntityId}.json";

        if (!file_exists($filePath)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($filePath), true);
        if (is_array($decoded) && isset($decoded['state'], $decoded['last_event_index'])) {
            return [
                'state' => $decoded['state'],
                'last_event_index' => (int)$decoded['last_event_index']
            ];
        }

        return null;
    }
}

