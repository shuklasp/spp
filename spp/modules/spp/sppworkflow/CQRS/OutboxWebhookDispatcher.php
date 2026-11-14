<?php

namespace SPPMod\SPPWorkflow\CQRS;

/**
 * OutboxWebhookDispatcher
 * Implements the Transactional Outbox pattern for asynchronous, resilient webhook dispatching.
 * Includes HMAC-SHA256 signature generation (X-SPP-Signature), exponential backoff retries, and DLQ handling.
 */
class OutboxWebhookDispatcher
{
    private static array $outboxStorage = []; // In-memory cache and test fallback storage

    /**
     * Clear in-memory outbox storage (for testing).
     */
    public static function clear(): void
    {
        self::$outboxStorage = [];
    }

    /**
     * Installs the CQRS outbox database table if it doesn't already exist.
     */
    private static function installDatabaseTable(\SPPMod\SPPDB\SPPDB $db, string $table): void
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
                id VARCHAR(64) PRIMARY KEY,
                event_name VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                target_url VARCHAR(1024) NOT NULL,
                secret VARCHAR(255) NOT NULL,
                attempts INTEGER DEFAULT 0,
                next_retry INTEGER NOT NULL,
                status VARCHAR(32) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id VARCHAR(64) PRIMARY KEY,
                event_name VARCHAR(100) NOT NULL,
                payload TEXT NOT NULL,
                target_url VARCHAR(1024) NOT NULL,
                secret VARCHAR(255) NOT NULL,
                attempts INT DEFAULT 0,
                next_retry INT NOT NULL,
                status VARCHAR(32) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status_retry (status, next_retry)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        $db->exec_squery($sql, $table);
    }

    /**
     * Queue a webhook delivery in the Outbox table.
     */
    public static function queueWebhook(string $eventName, array $payload, string $targetUrl, string $secret = 'spp_enterprise_secret'): bool
    {
        $item = [
            'id' => uniqid('wb_', true),
            'event' => $eventName,
            'payload' => $payload,
            'target_url' => $targetUrl,
            'secret' => $secret,
            'attempts' => 0,
            'next_retry' => time(),
            'status' => 'pending'
        ];

        self::$outboxStorage[] = $item;

        if (class_exists('\\SPP\\DB') || class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = null;
                if (class_exists('\\SPP\\DB')) {
                    try {
                        $db = \SPP\DB::getInstance();
                    } catch (\Throwable $e) {}
                }
                if (!$db && class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                    $db = new \SPPMod\SPPDB\SPPDB();
                }
                $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_cqrs_outbox');
                if (!$db->tableExists($table)) {
                    self::installDatabaseTable($db, $table);
                }

                $db->exec_squery(
                    "INSERT INTO %tab% (id, event_name, payload, target_url, secret, attempts, next_retry, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    $table,
                    [
                        $item['id'],
                        $item['event'],
                        json_encode($item['payload']),
                        $item['target_url'],
                        $item['secret'],
                        0,
                        $item['next_retry'],
                        'pending',
                        date('Y-m-d H:i:s')
                    ]
                );
            } catch (\Exception $e) {
                error_log("OutboxWebhookDispatcher DB Queue Failed: " . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Generate an HMAC-SHA256 cryptographic signature for the payload.
     */
    public static function generateSignature(array $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Process pending items in the outbox table.
     */
    public static function processOutbox(int $batchSize = 50): int
    {
        $count = 0;
        $now = time();

        // 1. Process from persistent database if available
        if (class_exists('\\SPP\\DB') || class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = null;
                if (class_exists('\\SPP\\DB')) {
                    try {
                        $db = \SPP\DB::getInstance();
                    } catch (\Throwable $e) {}
                }
                if (!$db && class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                    $db = new \SPPMod\SPPDB\SPPDB();
                }
                $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_cqrs_outbox');
                if ($db->tableExists($table)) {
                    $rows = $db->query(
                        "SELECT * FROM %tab% WHERE status = 'pending' AND next_retry <= ? ORDER BY next_retry ASC LIMIT ?",
                        $table,
                        [$now, $batchSize]
                    );

                    if (!empty($rows)) {
                        foreach ($rows as $row) {
                            $payload = is_string($row['payload']) ? json_decode($row['payload'], true) : $row['payload'];
                            $attempts = (int)$row['attempts'] + 1;
                            $signature = self::generateSignature($payload, $row['secret']);
                            $payloadJson = json_encode($payload);

                            // Non-blocking cURL dispatch
                            $ch = curl_init($row['target_url']);
                            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Content-Type: application/json',
                                'Content-Length: ' . strlen($payloadJson),
                                'X-SPP-Signature: ' . $signature,
                                'X-SPP-Event: ' . $row['event_name'],
                                'X-SPP-Attempt: ' . $attempts
                            ]);

                            $response = @curl_exec($ch);
                            $httpCode = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            @curl_close($ch);

                            $status = 'pending';
                            $nextRetry = $now;

                            if ($httpCode >= 200 && $httpCode < 300) {
                                $status = 'delivered';
                                $db->exec_squery("UPDATE %tab% SET status = ?, attempts = ? WHERE id = ?", $table, ['delivered', $attempts, $row['id']]);
                                $count++;
                            } else {
                                if ($attempts >= 5) {
                                    $status = 'dlq';
                                    $db->exec_squery("UPDATE %tab% SET status = ?, attempts = ? WHERE id = ?", $table, ['dlq', $attempts, $row['id']]);
                                } else {
                                    // Exponential backoff retry calculation: 2^attempts * 15 seconds
                                    $backoff = (2 ** $attempts) * 15;
                                    $nextRetry = $now + $backoff;
                                    $db->exec_squery("UPDATE %tab% SET attempts = ?, next_retry = ? WHERE id = ?", $table, [$attempts, $nextRetry, $row['id']]);
                                }
                            }

                            // Synchronize in-memory cache
                            foreach (self::$outboxStorage as &$memItem) {
                                if ($memItem['id'] === $row['id']) {
                                    $memItem['attempts'] = $attempts;
                                    $memItem['status'] = $status;
                                    $memItem['next_retry'] = $nextRetry;
                                    break;
                                }
                            }

                            if ($count >= $batchSize) {
                                break;
                            }
                        }

                        return $count;
                    }
                }
            } catch (\Exception $e) {
                error_log("OutboxWebhookDispatcher DB Processing Failed: " . $e->getMessage());
            }
        }

        // 2. Fallback in-memory processing for isolated tests or environment without DB
        foreach (self::$outboxStorage as &$item) {
            if ($item['status'] === 'pending' && $item['next_retry'] <= $now) {
                $item['attempts']++;
                $payloadJson = json_encode($item['payload']);
                $signature = self::generateSignature($item['payload'], $item['secret']);

                // Non-blocking cURL dispatch
                $ch = curl_init($item['target_url']);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payloadJson),
                    'X-SPP-Signature: ' . $signature,
                    'X-SPP-Event: ' . $item['event'],
                    'X-SPP-Attempt: ' . $item['attempts']
                ]);

                $response = @curl_exec($ch);
                $httpCode = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
                @curl_close($ch);

                if ($httpCode >= 200 && $httpCode < 300) {
                    $item['status'] = 'delivered';
                    $count++;
                } else {
                    if ($item['attempts'] >= 5) {
                        $item['status'] = 'dlq'; // Transition to Dead Letter Queue
                    } else {
                        // Exponential backoff retry calculation: 2^attempts * 15 seconds
                        $backoff = (2 ** $item['attempts']) * 15;
                        $item['next_retry'] = $now + $backoff;
                    }
                }

                if ($count >= $batchSize) {
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Retrieve current outbox table state for inspection/testing.
     */
    public static function getOutboxState(): array
    {
        if (!empty(self::$outboxStorage)) {
            return self::$outboxStorage;
        }

        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_cqrs_outbox');
                if ($db->tableExists($table)) {
                    $rows = $db->query("SELECT * FROM %tab% ORDER BY created_at DESC", $table);
                    if ($rows) {
                        $formatted = [];
                        foreach ($rows as $row) {
                            $formatted[] = [
                                'id' => $row['id'],
                                'event' => $row['event_name'],
                                'payload' => is_string($row['payload']) ? json_decode($row['payload'], true) : $row['payload'],
                                'target_url' => $row['target_url'],
                                'secret' => $row['secret'],
                                'attempts' => (int)$row['attempts'],
                                'next_retry' => (int)$row['next_retry'],
                                'status' => $row['status']
                            ];
                        }
                        return $formatted;
                    }
                }
            } catch (\Exception $e) {}
        }

        return self::$outboxStorage;
    }

    /**
     * Reset and clear outbox storage (for testing purposes).
     */
    public static function clearOutbox(): void
    {
        self::$outboxStorage = [];
        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            try {
                $db = new \SPPMod\SPPDB\SPPDB();
                $table = \SPPMod\SPPDB\SPPDB::sppTable('spp_cqrs_outbox');
                if ($db->tableExists($table)) {
                    $db->exec_squery("DELETE FROM %tab%", $table);
                }
            } catch (\Exception $e) {}
        }
    }
}
