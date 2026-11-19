<?php
declare(strict_types=1);

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\Polyglot\PolyglotJob;
use SPP\PolyglotBridge;

class PolyglotAsyncCommand extends Command
{
    protected string $name = 'polyglot:async';
    protected string $description = 'Internal command to execute polyglot calls asynchronously';
    protected bool $hidden = true;

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $payloadB64 = $this->getArgument($args, 0) ?? null;
        if (!$payloadB64) {
            return;
        }

        $payload = json_decode(base64_decode($payloadB64), true);
        if (!$payload) {
            return;
        }

        $jobId = $payload['job_id'] ?? null;
        if ($jobId) {
            $state = PolyglotJob::getState($jobId);
            if ($state) {
                $job = new PolyglotJob($state);
                $job->handle();
                return;
            }
        }

        $lang = $payload['lang'] ?? '';
        $module = $payload['module'] ?? '';
        $func = $payload['func'] ?? '';
        $funcArgs = $payload['args'] ?? [];
        $daemon = $payload['daemon'] ?? false;

        try {
            PolyglotBridge::call($lang, $module, $func, $funcArgs, $daemon);
        } catch (\Throwable $e) {
            error_log("PolyglotAsync Error: " . $e->getMessage());
        }
    }
}
