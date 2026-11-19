<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

if (!class_exists('\\SPP\\Core\\Job')) {
    $queueFile = dirname(__DIR__, 2) . '/modules/optional/sppqueue/src/SPPQueue.php';
    if (file_exists($queueFile)) {
        require_once $queueFile;
    }
}

use SPP\Core\Job;
use SPP\Core\Queue;
use SPP\PolyglotBridge;

/**
 * Class PolyglotJob
 *
 * Managed asynchronous job for SPP Polyglot routine execution.
 * Backed by SPP's Queue system and atomic persistent job state ledger.
 */
class PolyglotJob extends Job
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Resolves job storage directory.
     */
    public static function getStorageDir(): string
    {
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4));
        $dir = $appDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'jobs' . DIRECTORY_SEPARATOR . 'polyglot';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return realpath($dir) ?: $dir;
    }

    /**
     * Dispatches a new managed asynchronous polyglot job.
     */
    public static function dispatch(
        string $lang,
        string $module,
        string $func,
        array $args = [],
        bool $daemon = false,
        int $timeout = 60,
        int $retries = 3,
        ?string $correlationId = null
    ): string {
        $jobId = 'poly_' . bin2hex(random_bytes(12));
        $correlation = $correlationId ?? ('corr_' . bin2hex(random_bytes(8)));

        $data = [
            'job_id' => $jobId,
            'correlation_id' => $correlation,
            'lang' => $lang,
            'module' => $module,
            'func' => $func,
            'args' => $args,
            'daemon' => $daemon,
            'timeout' => $timeout,
            'retries' => $retries,
            'attempts' => 0,
            'status' => self::STATUS_QUEUED,
            'created_at' => microtime(true),
            'started_at' => null,
            'completed_at' => null,
            'result' => null,
            'error' => null,
        ];

        self::saveState($jobId, $data);

        // Push to framework Queue if available
        $job = new self($data);
        if (class_exists(Queue::class)) {
            Queue::push($job);
        }

        return $jobId;
    }

    /**
     * Executes the queued job.
     */
    public function handle(): void
    {
        $jobId = $this->data['job_id'] ?? '';
        if (!$jobId) {
            return;
        }

        $state = self::getState($jobId);
        if (!$state || $state['status'] === self::STATUS_CANCELLED) {
            return;
        }

        $state['status'] = self::STATUS_RUNNING;
        $state['started_at'] = microtime(true);
        $state['attempts'] = ($state['attempts'] ?? 0) + 1;
        self::saveState($jobId, $state);

        // Trace Propagation
        if (class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
            \SPPMod\SPPReport\W3CTraceContext::startTrace("PolyglotJob:{$state['lang']}:{$state['func']}");
        }

        $lang = $state['lang'];
        $module = $state['module'];
        $func = $state['func'];
        $args = $state['args'] ?? [];
        $daemon = $state['daemon'] ?? false;

        try {
            $callRes = PolyglotBridge::call($lang, $module, $func, $args, $daemon);

            if (isset($callRes['success']) && $callRes['success']) {
                $state['status'] = self::STATUS_COMPLETED;
                $state['result'] = $callRes['data'] ?? null;
                $state['completed_at'] = microtime(true);
            } else {
                $errorMsg = $callRes['error'] ?? 'Polyglot execution failed without error message.';
                if ($state['attempts'] < $state['retries']) {
                    // Retry
                    $state['status'] = self::STATUS_QUEUED;
                    $state['error'] = "Attempt {$state['attempts']} failed: {$errorMsg}";
                    self::saveState($jobId, $state);
                    if (class_exists(Queue::class)) {
                        Queue::push(new self($state), 2); // 2s delay
                    }
                    return;
                } else {
                    $state['status'] = self::STATUS_FAILED;
                    $state['error'] = $errorMsg;
                    $state['completed_at'] = microtime(true);
                }
            }
        } catch (\Throwable $e) {
            if ($state['attempts'] < $state['retries']) {
                $state['status'] = self::STATUS_QUEUED;
                $state['error'] = "Attempt {$state['attempts']} exception: " . $e->getMessage();
                self::saveState($jobId, $state);
                if (class_exists(Queue::class)) {
                    Queue::push(new self($state), 2);
                }
                return;
            } else {
                $state['status'] = self::STATUS_FAILED;
                $state['error'] = $e->getMessage();
                $state['completed_at'] = microtime(true);
            }
        }

        self::saveState($jobId, $state);
    }

    /**
     * Reads job state ledger.
     */
    public static function getState(string $jobId): ?array
    {
        $file = self::getStorageDir() . DIRECTORY_SEPARATOR . basename($jobId) . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Atomically writes job state ledger.
     */
    public static function saveState(string $jobId, array $state): void
    {
        $storageDir = self::getStorageDir();
        $targetFile = $storageDir . DIRECTORY_SEPARATOR . basename($jobId) . '.json';
        $tmpFile = $targetFile . '.tmp.' . bin2hex(random_bytes(4));

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @file_put_contents($tmpFile, $json);
        @rename($tmpFile, $targetFile);
    }

    /**
     * Cancels a queued job.
     */
    public static function cancel(string $jobId): bool
    {
        $state = self::getState($jobId);
        if (!$state) {
            return false;
        }

        if (in_array($state['status'], [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED], true)) {
            return false;
        }

        $state['status'] = self::STATUS_CANCELLED;
        $state['completed_at'] = microtime(true);
        self::saveState($jobId, $state);
        return true;
    }
}
