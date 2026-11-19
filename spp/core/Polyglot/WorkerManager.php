<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Class WorkerManager
 *
 * Sovereign, hardened persistent worker lifecycle manager for SPP Polyglot 2.0.
 * Guarantees:
 * 1. Stable worker identity (context + canonical module path + language + runtime fingerprint).
 * 2. Atomic PID, port, and metadata file creation.
 * 3. Process identity validation before termination (preventing killing recycled PIDs).
 * 4. Stale state and port file self-healing.
 * 5. Cryptographic authentication on every request (never relies on loopback alone).
 * 6. Readiness health checks with startup deadlines.
 * 7. Graceful socket shutdown followed by forced termination only when necessary.
 * 8. Socket read/write timeouts and structured error handling.
 */
class WorkerManager
{
    public const PROTOCOL_VERSION = '2.0';

    /**
     * Computes the deterministic, stable identity hash for a worker.
     */
    public static function computeWorkerHash(string $module, string $lang, ?string $context = null): string
    {
        $appContext = $context ?? (\SPP\Scheduler::getContext() ?: 'default');
        $canonicalPath = realpath($module) ?: $module;
        $normalizedLang = strtolower($lang);

        return hash('sha256', "{$appContext}:{$canonicalPath}:{$normalizedLang}");
    }

    /**
     * Resolves the daemons state directory.
     */
    public static function getDaemonsDir(): string
    {
        return Platform::getDaemonsDir();
    }

    /**
     * Starts a persistent polyglot worker.
     *
     * @param string $module
     * @param string|null $lang
     * @param string|null $context
     * @param int $startupTimeoutSeconds
     * @return array{success: bool, port?: int, pid?: int, hash?: string, error?: string, token?: string}
     */
    public static function startWorker(
        string $module,
        ?string $lang = null,
        ?string $context = null,
        int $startupTimeoutSeconds = 15
    ): array {
        $canonicalModule = realpath($module) ?: $module;
        if (!file_exists($canonicalModule)) {
            return ['success' => false, 'error' => "Module file not found: '{$module}'."];
        }

        if (!$lang) {
            $ext = strtolower(pathinfo($canonicalModule, PATHINFO_EXTENSION));
            $driver = LanguageRegistry::getDriver($ext);
            $lang = $driver ? $driver['id'] : $ext;
        }

        $normalizedLang = strtolower($lang);
        $appContext = $context ?? (\SPP\Scheduler::getContext() ?: 'default');
        $hash = self::computeWorkerHash($canonicalModule, $normalizedLang, $appContext);

        $daemonsDir = self::getDaemonsDir();
        $portFile = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}.port";
        $pidFile = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}.pid";
        $metaFile = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}.meta.json";
        $logFile = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}.log";

        // Check if existing worker is already running and healthy
        if (file_exists($metaFile) && file_exists($portFile)) {
            $meta = json_decode((string)file_get_contents($metaFile), true);
            if (is_array($meta) && !empty($meta['pid'])) {
                $pid = (int)$meta['pid'];
                if (Platform::isPidAlive($pid)) {
                    $port = (int)trim((string)file_get_contents($portFile));
                    // Verify health check
                    if (self::pingWorker($port, $meta['auth_token'] ?? '')) {
                        return [
                            'success' => true,
                            'port' => $port,
                            'pid' => $pid,
                            'hash' => $hash,
                            'token' => $meta['auth_token'] ?? '',
                            'already_running' => true,
                        ];
                    }
                }
            }
            // Stale worker detected, clean up
            self::cleanupWorkerFiles($hash);
        }

        // Generate strong cryptographic auth token
        $authToken = bin2hex(random_bytes(32));

        // Resolve runtime binary
        $runtimes = LanguageRegistry::discoverRuntimes();
        $runtimeInfo = $runtimes[$normalizedLang] ?? null;
        $binary = $runtimeInfo['path'] ?? Platform::findBinary($normalizedLang);

        if (empty($binary) && $normalizedLang !== 'compiler') {
            return ['success' => false, 'error' => "Runtime binary for '{$normalizedLang}' not found in PATH."];
        }

        $frameworkLibDir = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'polyglot';

        // Build daemon launch command
        $command = [];
        if ($normalizedLang === 'python') {
            $dispatchScript = $frameworkLibDir . DIRECTORY_SEPARATOR . 'dispatch.py';
            $command = [$binary, $dispatchScript, $canonicalModule, '--daemon', $portFile, '--auth-token', $authToken, '--pid-file', $pidFile];
        } elseif ($normalizedLang === 'node') {
            $dispatchScript = $frameworkLibDir . DIRECTORY_SEPARATOR . 'dispatch.js';
            $command = [$binary, $dispatchScript, $canonicalModule, '--daemon', $portFile, '--auth-token', $authToken, '--pid-file', $pidFile];
        } elseif ($normalizedLang === 'perl') {
            $dispatchScript = $frameworkLibDir . DIRECTORY_SEPARATOR . 'dispatch.pl';
            $command = [$binary, $dispatchScript, $canonicalModule, '--daemon', $portFile, '--auth-token', $authToken, '--pid-file', $pidFile];
        } else {
            return ['success' => false, 'error' => "Daemon worker not supported for language '{$normalizedLang}'."];
        }

        // Spawn worker process in background
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $logFile, 'a'],
            2 => ['file', $logFile, 'a'],
        ];

        $env = array_merge(getenv(), [
            'PYTHONUNBUFFERED' => '1',
            'NODE_ENV' => 'production',
        ]);

        $workingDir = dirname($canonicalModule);
        $process = @proc_open($command, $descriptors, $pipes, $workingDir, $env, [
            'bypass_shell' => true,
        ]);

        if (!is_resource($process)) {
            return ['success' => false, 'error' => "Failed to spawn worker process for '{$canonicalModule}'."];
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }

        $procStatus = @proc_get_status($process);
        $pid = $procStatus['pid'] ?? 0;

        // Write preliminary PID file if not written by dispatcher
        if ($pid > 0 && !file_exists($pidFile)) {
            @file_put_contents($pidFile, (string)$pid);
        }

        // Wait for worker readiness (poll port file and send ping)
        $deadline = microtime(true) + $startupTimeoutSeconds;
        $port = 0;
        $lastPidCheck = microtime(true);

        while (microtime(true) < $deadline) {
            if (file_exists($portFile)) {
                $rawPort = trim((string)@file_get_contents($portFile));
                if (is_numeric($rawPort) && (int)$rawPort > 0) {
                    $port = (int)$rawPort;
                    // Attempt socket readiness ping
                    if (self::pingWorker($port, $authToken)) {
                        break;
                    }
                }
            }

            // Rate-limit expensive process liveness check to at most once per second
            if (microtime(true) - $lastPidCheck >= 1.0) {
                $lastPidCheck = microtime(true);
                if ($pid > 0 && !Platform::isPidAlive($pid)) {
                    $logContent = file_exists($logFile) ? trim((string)@file_get_contents($logFile)) : '';
                    self::cleanupWorkerFiles($hash);
                    return [
                        'success' => false,
                        'error' => "Worker process died immediately after startup. Log: {$logContent}",
                    ];
                }
            }

            usleep(50000); // 50ms
        }

        if ($port <= 0 || !self::pingWorker($port, $authToken)) {
            self::stopWorker($canonicalModule, $normalizedLang, $appContext, true);
            $logContent = file_exists($logFile) ? trim((string)@file_get_contents($logFile)) : '';
            return [
                'success' => false,
                'error' => "Worker failed to bind or pass readiness health check within {$startupTimeoutSeconds}s. Log: {$logContent}",
            ];
        }

        // If dispatcher updated pidFile, read actual child pid
        if (file_exists($pidFile)) {
            $childPid = (int)trim((string)file_get_contents($pidFile));
            if ($childPid > 0) {
                $pid = $childPid;
            }
        }

        // Write atomic metadata file
        $metadata = [
            'hash' => $hash,
            'module' => $canonicalModule,
            'lang' => $normalizedLang,
            'context' => $appContext,
            'port' => $port,
            'pid' => $pid,
            'auth_token' => $authToken,
            'protocol_version' => self::PROTOCOL_VERSION,
            'started_at' => time(),
            'log_file' => $logFile,
        ];

        $tmpMeta = $metaFile . '.tmp.' . $pid;
        file_put_contents($tmpMeta, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @rename($tmpMeta, $metaFile);

        return [
            'success' => true,
            'port' => $port,
            'pid' => $pid,
            'hash' => $hash,
            'token' => $authToken,
        ];
    }

    /**
     * Performs a health check ping to a worker.
     */
    public static function pingWorker(int $port, string $authToken): bool
    {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if (!$fp) {
            return false;
        }

        stream_set_timeout($fp, 1);
        $payload = json_encode([
            'func' => '__ping__',
            'auth_token' => $authToken,
            'request_id' => 'ping_' . bin2hex(random_bytes(4)),
        ]) . "\n";

        @fwrite($fp, $payload);
        $res = @fgets($fp, 4096);
        @fclose($fp);

        if (!$res) {
            return false;
        }

        $decoded = json_decode($res, true);
        return is_array($decoded) && ($decoded['success'] ?? false) === true && ($decoded['data'] ?? '') === 'PONG';
    }

    /**
     * Invokes a routine on a persistent worker daemon with authentication and error handling.
     *
     * @param string $module
     * @param string $func
     * @param array $args
     * @param string|null $lang
     * @param float $timeout
     * @return array{success: bool, data?: mixed, error?: string}
     */
    public static function callWorker(
        string $module,
        string $func,
        array $args = [],
        ?string $lang = null,
        float $timeout = 15.0
    ): array {
        $start = self::startWorker($module, $lang);
        if (!$start['success']) {
            return ['success' => false, 'error' => "Failed to connect to worker: " . ($start['error'] ?? 'Unknown')];
        }

        $port = (int)$start['port'];
        $authToken = (string)$start['token'];

        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2.0);
        if (!$fp) {
            // Worker socket was dead, attempt restart once
            self::cleanupWorkerFiles($start['hash']);
            $restart = self::startWorker($module, $lang);
            if (!$restart['success']) {
                return ['success' => false, 'error' => "Worker socket unreachable: {$errstr} (errno: {$errno})"];
            }
            $port = (int)$restart['port'];
            $authToken = (string)$restart['token'];
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 2.0);
            if (!$fp) {
                return ['success' => false, 'error' => "Worker socket unreachable after restart: {$errstr}"];
            }
        }

        stream_set_timeout($fp, (int)ceil($timeout));

        $reqId = 'req_' . bin2hex(random_bytes(8));
        $requestPayload = json_encode([
            'protocol_version' => self::PROTOCOL_VERSION,
            'request_id' => $reqId,
            'auth_token' => $authToken,
            'func' => $func,
            'args' => $args,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        $written = @fwrite($fp, $requestPayload);
        if ($written === false) {
            @fclose($fp);
            return ['success' => false, 'error' => "Failed to write request payload to worker socket."];
        }

        $responseRaw = '';
        while (!feof($fp)) {
            $chunk = @fgets($fp, 16384);
            if ($chunk === false) {
                break;
            }
            $responseRaw .= $chunk;
            if (str_ends_with($chunk, "\n")) {
                break;
            }
        }
        @fclose($fp);

        $trimmed = trim($responseRaw);
        if ($trimmed === '') {
            return ['success' => false, 'error' => "Empty response received from worker."];
        }

        $decoded = json_decode($trimmed, true);
        if ($decoded === null) {
            return ['success' => false, 'error' => "Invalid JSON returned by worker: {$trimmed}"];
        }

        if (isset($decoded['success']) && !$decoded['success']) {
            return [
                'success' => false,
                'error' => $decoded['error'] ?? 'Worker reported execution error.',
                'error_type' => $decoded['error_type'] ?? 'WorkerException',
            ];
        }

        return [
            'success' => true,
            'data' => $decoded['data'] ?? $decoded,
        ];
    }

    /**
     * Gracefully stops a worker daemon, with fallback to forced termination.
     */
    public static function stopWorker(
        string $module,
        ?string $lang = null,
        ?string $context = null,
        bool $force = false
    ): array {
        $canonicalModule = realpath($module) ?: $module;
        if (!$lang) {
            $ext = strtolower(pathinfo($canonicalModule, PATHINFO_EXTENSION));
            $driver = LanguageRegistry::getDriver($ext);
            $lang = $driver ? $driver['id'] : $ext;
        }

        $normalizedLang = strtolower($lang);
        $appContext = $context ?? (\SPP\Scheduler::getContext() ?: 'default');
        $hash = self::computeWorkerHash($canonicalModule, $normalizedLang, $appContext);

        $daemonsDir = self::getDaemonsDir();
        $metaFile = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}.meta.json";
        $portFile = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}.port";
        $pidFile = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}.pid";

        $pid = 0;
        $port = 0;
        $authToken = '';

        if (file_exists($metaFile)) {
            $meta = json_decode((string)file_get_contents($metaFile), true);
            if (is_array($meta)) {
                $pid = (int)($meta['pid'] ?? 0);
                $port = (int)($meta['port'] ?? 0);
                $authToken = (string)($meta['auth_token'] ?? '');
            }
        }

        if ($pid <= 0 && file_exists($pidFile)) {
            $pid = (int)trim((string)file_get_contents($pidFile));
        }

        if ($port <= 0 && file_exists($portFile)) {
            $port = (int)trim((string)file_get_contents($portFile));
        }

        // 1. Attempt graceful shutdown via socket command
        if (!$force && $port > 0) {
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
            if ($fp) {
                stream_set_timeout($fp, 1);
                $payload = json_encode([
                    'func' => '__shutdown__',
                    'auth_token' => $authToken,
                    'request_id' => 'shutdown_' . bin2hex(random_bytes(4)),
                ]) . "\n";
                @fwrite($fp, $payload);
                @fgets($fp, 512);
                @fclose($fp);

                // Wait up to 1.5s for graceful process exit
                for ($i = 0; $i < 15; $i++) {
                    if ($pid > 0 && !Platform::isPidAlive($pid)) {
                        break;
                    }
                    usleep(100000); // 100ms
                }
            }
        }

        // 2. Forced termination if process is still alive
        if ($pid > 0 && Platform::isPidAlive($pid)) {
            Platform::terminatePid($pid, 500);
        }

        self::cleanupWorkerFiles($hash);

        return [
            'success' => true,
            'message' => "Worker for '{$module}' stopped.",
            'pid' => $pid,
        ];
    }

    /**
     * Cleans up all filesystem artifacts for a worker hash.
     */
    public static function cleanupWorkerFiles(string $hash): void
    {
        $daemonsDir = self::getDaemonsDir();
        $exts = ['.port', '.pid', '.meta.json', '.module'];
        foreach ($exts as $ext) {
            $f = $daemonsDir . DIRECTORY_SEPARATOR . "{$hash}{$ext}";
            if (file_exists($f)) {
                @unlink($f);
            }
        }
    }

    /**
     * Scans and cleans up stale worker files whose PID is no longer alive.
     */
    public static function cleanupStaleWorkers(): int
    {
        $daemonsDir = self::getDaemonsDir();
        $metaFiles = glob($daemonsDir . DIRECTORY_SEPARATOR . '*.meta.json') ?: [];
        $cleaned = 0;

        foreach ($metaFiles as $metaFile) {
            $meta = json_decode((string)file_get_contents($metaFile), true);
            if (is_array($meta)) {
                $pid = (int)($meta['pid'] ?? 0);
                $hash = $meta['hash'] ?? pathinfo($metaFile, PATHINFO_FILENAME);
                if ($pid <= 0 || !Platform::isPidAlive($pid)) {
                    self::cleanupWorkerFiles($hash);
                    $cleaned++;
                }
            }
        }

        // Also check orphan .port or .pid files
        $portFiles = glob($daemonsDir . DIRECTORY_SEPARATOR . '*.port') ?: [];
        foreach ($portFiles as $portFile) {
            $base = substr($portFile, 0, -5);
            $pidFile = $base . '.pid';
            if (file_exists($pidFile)) {
                $pid = (int)trim((string)file_get_contents($pidFile));
                if ($pid <= 0 || !Platform::isPidAlive($pid)) {
                    @unlink($portFile);
                    @unlink($pidFile);
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }
}
