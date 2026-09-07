<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use App\SPPDocs\Services\AuditLogService;

/**
 * Class SPPDocsAuditVerifyCommand
 * Verifies the cryptographic SHA-256 hash chain integrity of an SPPDocs project audit trail.
 */
class SPPDocsAuditVerifyCommand extends Command
{
    protected string $name = 'sppdocs:audit:verify';
    protected string $description = 'Verify the cryptographic SHA-256 hash chain integrity of an SPPDocs project audit trail';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $project = 'demo-app';
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--project=')) {
                $project = substr($arg, 10);
            }
        }

        $appDir = dirname(__DIR__, 2);
        $issuesDir = $appDir . '/docs/' . $project . '/issues';

        echo "🔒 Verifying Cryptographic Audit Trail for '{$project}'...\n";
        echo "   Audit Chain File: {$issuesDir}/audit_chain.jsonl\n\n";

        $res = AuditLogService::verifyChain($issuesDir);

        if ($res['valid']) {
            echo "✅ Audit Trail Integrity Verified!\n";
            echo "   Total Events Verified: " . ($res['total'] ?? 0) . "\n";
            if (!empty($res['latest_hash'])) {
                echo "   Latest Merkle Hash:    " . $res['latest_hash'] . "\n";
            }
            echo "   Status:                100% UNTAMPERED\n";
        } else {
            echo "🚨 AUDIT INTEGRITY FAILURE! Tampering detected!\n";
            echo "   Error at Line: " . ($res['error_line'] ?? '?') . "\n";
            echo "   Reason:        " . ($res['reason'] ?? 'Unknown hash mismatch') . "\n";
        }
    }
}
