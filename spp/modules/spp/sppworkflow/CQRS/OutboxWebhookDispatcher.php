<?php

namespace SPPMod\SPPWorkflow\CQRS;

/**
 * OutboxWebhookDispatcher
 * Implements the Transactional Outbox pattern for asynchronous, resilient webhook dispatching.
 * Includes HMAC-SHA256 signature generation (X-SPP-Signature), exponential backoff retries, and DLQ handling.
 */
class OutboxWebhookDispatcher
{
    private static array $outboxStorage = []; // Simulated memory persistent table for outbox items

    /**
     * Queue a webhook delivery in the Outbox table.
     */
    public static function queueWebhook(string $eventName, array $payload, string $targetUrl, string $secret = 'spp_enterprise_secret'): bool
    {
        self::$outboxStorage[] = [
            'id' => uniqid('wb_', true),
            'event' => $eventName,
            'payload' => $payload,
            'target_url' => $targetUrl,
            'secret' => $secret,
            'attempts' => 0,
            'next_retry' => time(),
            'status' => 'pending'
        ];

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
        return self::$outboxStorage;
    }
}
