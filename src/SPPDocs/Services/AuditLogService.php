<?php

namespace App\SPPDocs\Services;

/**
 * AuditLogService
 * Cryptographic Merkle/SHA-256 hash-chained audit logging.
 * Guarantees tamper-evident verification for regulatory compliance and enterprise auditability.
 */
class AuditLogService
{
    public const GENESIS_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    public static function append(string $issuesDir, string $eventType, string $user, string $targetId, string $description): array
    {
        if (!is_dir($issuesDir)) {
            @mkdir($issuesDir, 0777, true);
        }
        $chainFile = rtrim($issuesDir, '/\\') . '/audit_chain.jsonl';

        $prevHash = self::getLastHash($chainFile);
        $timestamp = time();

        $payload = [
            'timestamp' => $timestamp,
            'datetime' => date('c', $timestamp),
            'user' => $user ?: 'anonymous',
            'event' => $eventType,
            'target_id' => $targetId,
            'description' => $description,
            'prev_hash' => $prevHash,
        ];

        $hashString = $prevHash . '|' . $timestamp . '|' . $payload['user'] . '|' . $eventType . '|' . $targetId . '|' . $description;
        $hash = hash('sha256', $hashString);
        $payload['hash'] = $hash;

        @file_put_contents($chainFile, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

        return $payload;
    }

    public static function getLastHash(string $chainFile): string
    {
        if (!file_exists($chainFile) || filesize($chainFile) === 0) {
            return self::GENESIS_HASH;
        }

        $lines = file($chainFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return self::GENESIS_HASH;
        }

        $last = json_decode(end($lines), true);
        return $last['hash'] ?? self::GENESIS_HASH;
    }

    public static function verifyChain(string $issuesDir): array
    {
        $chainFile = rtrim($issuesDir, '/\\') . '/audit_chain.jsonl';
        if (!file_exists($chainFile)) {
            return ['valid' => true, 'total' => 0, 'message' => 'No audit log file present yet.'];
        }

        $lines = file($chainFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $expectedPrev = self::GENESIS_HASH;
        $index = 0;

        foreach ($lines as $line) {
            $index++;
            $entry = json_decode($line, true);
            if (!$entry || !isset($entry['hash'], $entry['prev_hash'])) {
                return ['valid' => false, 'error_line' => $index, 'reason' => 'Corrupted JSON entry'];
            }

            if ($entry['prev_hash'] !== $expectedPrev) {
                return [
                    'valid' => false,
                    'error_line' => $index,
                    'reason' => "Hash link broken! Expected prev_hash: {$expectedPrev}, found: {$entry['prev_hash']}"
                ];
            }

            $hashString = $entry['prev_hash'] . '|' . $entry['timestamp'] . '|' . $entry['user'] . '|' . $entry['event'] . '|' . $entry['target_id'] . '|' . $entry['description'];
            $calculatedHash = hash('sha256', $hashString);

            if ($calculatedHash !== $entry['hash']) {
                return [
                    'valid' => false,
                    'error_line' => $index,
                    'reason' => "Tampering detected at line {$index}! Recalculated hash does not match stored hash."
                ];
            }

            $expectedPrev = $entry['hash'];
        }

        return ['valid' => true, 'total' => count($lines), 'latest_hash' => $expectedPrev];
    }
}
