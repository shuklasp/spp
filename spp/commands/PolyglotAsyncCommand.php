<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

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
        if (!$payloadB64) return;

        $payload = json_decode(base64_decode($payloadB64), true);
        if (!$payload) return;

        $lang = $payload['lang'];
        $module = $payload['module'];
        $func = $payload['func'];
        $funcArgs = $payload['args'] ?? [];
        $daemon = $payload['daemon'] ?? false;

        // Execute it. We don't return the result anywhere, it's fire-and-forget.
        try {
            \SPP\PolyglotBridge::call($lang, $module, $func, $funcArgs, $daemon);
        } catch (\Exception $e) {
            // Log it in a real app, here we just exit
            error_log("PolyglotAsync Error: " . $e->getMessage());
        }
    }
}
