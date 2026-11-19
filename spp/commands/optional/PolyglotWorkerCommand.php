<?php
declare(strict_types=1);

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\Polyglot\WorkerManager;
use SPP\Core\Polyglot\Platform;

class PolyglotWorkerCommand extends Command
{
    protected string $name = 'polyglot:worker';
    protected string $description = 'Manage Polyglot persistent workers';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? null;
        $module = $this->getArgument($args, 1) ?? null;
        $lang = $this->getArgument($args, 2) ?? null;

        if (!$action || !in_array($action, ['start', 'stop', 'restart', 'status', 'cleanup', 'async'], true)) {
            echo "Usage: php spp.php polyglot:worker [start|stop|restart|status|cleanup] <module> [<lang>]\n";
            return;
        }

        if ($action === 'cleanup') {
            $cleaned = WorkerManager::cleanupStaleWorkers();
            echo "Cleaned up {$cleaned} stale worker artifact(s).\n";
            return;
        }

        if ($action === 'async') {
            $this->executeAsync($args);
            return;
        }

        if ($action === 'status') {
            $daemonsDir = WorkerManager::getDaemonsDir();
            $metaFiles = glob($daemonsDir . DIRECTORY_SEPARATOR . '*.meta.json') ?: [];
            if (empty($metaFiles)) {
                echo "No running polyglot workers found.\n";
            } else {
                echo "Active Polyglot Workers:\n";
                echo "+--------------------------------------+---------+-------+----------+------------------------------------------------+\n";
                echo "| Worker ID                            | Lang    | PID   | Port     | Module                                         |\n";
                echo "+--------------------------------------+---------+-------+----------+------------------------------------------------+\n";
                foreach ($metaFiles as $metaFile) {
                    $meta = json_decode((string)file_get_contents($metaFile), true);
                    if (is_array($meta)) {
                        $pid = (int)($meta['pid'] ?? 0);
                        $isAlive = Platform::isPidAlive($pid);
                        if (!$isAlive) {
                            WorkerManager::cleanupWorkerFiles($meta['hash'] ?? '');
                            continue;
                        }
                        $id = substr($meta['hash'] ?? basename($metaFile, '.meta.json'), 0, 36);
                        $l = $meta['lang'] ?? 'unknown';
                        $p = (string)($meta['port'] ?? 0);
                        $mod = substr($meta['module'] ?? '', -46);
                        echo "| " . str_pad($id, 36) . " | " . str_pad($l, 7) . " | " . str_pad((string)$pid, 5) . " | " . str_pad($p, 8) . " | " . str_pad($mod, 46) . " |\n";
                    }
                }
                echo "+--------------------------------------+---------+-------+----------+------------------------------------------------+\n";
            }
            return;
        }

        if (!$module) {
            echo "Module path required for action {$action}\n";
            return;
        }

        if ($action === 'start') {
            echo "Starting worker for {$module}...\n";
            $res = WorkerManager::startWorker($module, $lang);
            if ($res['success']) {
                echo "✔ Worker started (PID: {$res['pid']}, Port: {$res['port']})\n";
            } else {
                echo "✖ Failed to start worker: " . ($res['error'] ?? 'Unknown error') . "\n";
            }
        } elseif ($action === 'stop') {
            $res = WorkerManager::stopWorker($module, $lang);
            echo $res['message'] . "\n";
        } elseif ($action === 'restart') {
            WorkerManager::stopWorker($module, $lang);
            usleep(200000); // 200ms
            $res = WorkerManager::startWorker($module, $lang);
            if ($res['success']) {
                echo "✔ Worker restarted (PID: {$res['pid']}, Port: {$res['port']})\n";
            } else {
                echo "✖ Failed to restart worker: " . ($res['error'] ?? 'Unknown error') . "\n";
            }
        }
    }

    private function executeAsync(array $args): void
    {
        $payloadArg = $this->getArgument($args, 1);
        if (empty($payloadArg)) {
            error_log("PolyglotAsync: Missing payload.");
            return;
        }

        $payload = json_decode(base64_decode($payloadArg), true);
        if (!$payload) {
            error_log("PolyglotAsync: Invalid payload.");
            return;
        }

        $lang = $payload['lang'];
        $module = $payload['module'];
        $func = $payload['func'];
        $funcArgs = $payload['args'] ?? [];
        $daemon = $payload['daemon'] ?? false;

        \SPP\PolyglotBridge::call($lang, $module, $func, $funcArgs, $daemon);
    }
}
