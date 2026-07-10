<?php

namespace SPPMod\SPPWorkflow\Commands;

use SPP\CLI\Command;
use SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher;

/**
 * DispatchWebhooksCommand
 * CLI daemon for processing pending outbox webhooks in a background worker loop.
 */
class DispatchWebhooksCommand extends Command
{
    protected string $name = 'cqrs:webhooks:dispatch';
    protected string $description = 'Dispatch pending transactional outbox webhooks to subscribers with HMAC-SHA256 signature verification and exponential backoff retries';

    /**
     * Mandatory CLI SAPI Guarding to ensure zero-trust security execution.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        echo "\033[32mINFO:\033[0m Starting SPP CQRS Outbox Webhook Dispatcher Daemon...\n";

        if (!class_exists('\\SPPMod\\SPPWorkflow\\CQRS\\OutboxWebhookDispatcher')) {
            require_once dirname(__DIR__) . '/CQRS/OutboxWebhookDispatcher.php';
        }

        $batchSize = 50;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--batch=')) {
                $batchSize = (int)substr($arg, 8);
            }
        }

        echo "Processing batch size: \033[36m{$batchSize}\033[0m\n";

        // Process pending webhooks
        $processed = OutboxWebhookDispatcher::processOutbox($batchSize);

        echo "\033[32mSUCCESS:\033[0m Outbox webhook dispatching cycle complete. Delivered: {$processed} webhooks.\n";
    }
}
