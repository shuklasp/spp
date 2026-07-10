<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\Async\AsyncWorker;

/**
 * AppServeAsyncCommand
 * Boots the SPP persistent memory asynchronous coroutine runtime.
 */
class AppServeAsyncCommand extends Command
{
    protected string $name = 'serve:async';
    protected string $description = 'Boot the persistent memory asynchronous coroutine runtime (FrankenPHP/OpenSwoole)';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $appName = 'default';
        $port = 8080;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--app=')) {
                $appName = substr($arg, 6);
            } elseif (str_starts_with($arg, '--port=')) {
                $port = (int)substr($arg, 7);
            }
        }

        if (!class_exists('\SPP\Core\Async\AsyncWorker')) {
            require_once SPP_APP_DIR . '/spp/core/Async/AsyncWorker.php';
        }

        AsyncWorker::serve($appName, $port);
    }
}
