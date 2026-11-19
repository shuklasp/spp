<?php
declare(strict_types=1);

namespace SPP\Core\Apps;

/**
 * Class AppSupervisor
 *
 * Sovereign, Cross-Platform Multi-Language Process Supervisor.
 * Manages lifecycle, dynamic loopback port allocation, ambient environment
 * injection, healthcheck polling, and log streaming for guest application daemons.
 *
 * Supported OS: Windows, Linux, macOS. Zero external supervisor required.
 *
 * @package SPP\Core\Apps
 * @author Satya Prakash Shukla
 */
class AppSupervisor
{
    protected static ?string $storageDir = null;
    protected static ?string $logDir = null;

    /**
     * Resolves and ensures runtime state directory.
     */
    public static function getStorageDir(): string
    {
        if (self::$storageDir !== null) {
            return self::$storageDir;
        }

        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname($baseDir);
        $dir = $appDir . '/var/run/apps';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        self::$storageDir = $dir;
        return self::$storageDir;
    }

    /**
     * Resolves and ensures application log directory.
     */
    public static function getLogDir(): string
    {
        if (self::$logDir !== null) {
            return self::$logDir;
        }

        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname($baseDir);
        $dir = $appDir . '/var/logs/apps';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        self::$logDir = $dir;
        return self::$logDir;
    }

    /**
     * Determines whether an application requires a persistent background daemon.
     *
     * Request-driven applications (native SPP, static, and PHP/PHP-FPM without explicit CLI commands)
     * are executed on-demand by the web server or PHP runtime and do not run as managed daemons.
     *
     * @param array<string, mixed> $app
     * @return bool
     */
    public static function isDaemon(array $app): bool
    {
        if (!empty($app['command'])) {
            return true;
        }

        $appType = strtolower((string)($app['type'] ?? 'node'));
        return !in_array($appType, ['spp', 'php', 'php-fpm', 'static'], true);
    }

    /**
     * Starts an application daemon.
     *
     * @param string $appId
     * @return array{success: bool, pid?: int, port?: int, message: string, error?: string}
     */
    public static function start(string $appId): array
    {
        $app = ForeignAppRegistry::getApp($appId);
        if (!$app) {
            return ['success' => false, 'error' => "Application '{$appId}' is not registered."];
        }

        if (!self::isDaemon($app)) {
            $appType = strtolower((string)($app['type'] ?? 'php'));
            return [
                'success' => true,
                'message' => "Application '{$appId}' ({$appType}) is request-driven by the PHP/web server runtime and does not require a background daemon.",
            ];
        }

        if (self::isRunning($appId)) {
            $info = self::getAppRuntimeInfo($appId);
            return [
                'success' => true,
                'pid'     => $info['pid'] ?? 0,
                'port'    => $info['port'] ?? 0,
                'message' => "Application '{$appId}' is already running (PID: " . ($info['pid'] ?? 'unknown') . ").",
            ];
        }

        // Clean up stale state file if previous process is no longer running
        $staleState = self::getStorageDir() . '/' . $appId . '.state.json';
        if (file_exists($staleState)) {
            @unlink($staleState);
        }

        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname($baseDir);

        // 1. Resolve Working Directory
        $workingDirRel = $app['working_dir'] ?? ('apps/' . $appId);
        $workingDir = realpath($appDir . '/' . $workingDirRel) ?: ($appDir . '/' . $workingDirRel);
        if (!is_dir($workingDir)) {
            @mkdir($workingDir, 0755, true);
        }

        // 2. Allocate Dynamic Port or Socket
        $port = self::allocatePort($app);

        // 3. Generate Ephemeral App Security Token
        $token = bin2hex(random_bytes(24));

        // 4. Build Ambient Environment Variables
        $ambientEnv = [
            'PORT'              => (string)$port,
            'SPP_HOST'          => '1',
            'SPP_VERSION'       => '2.0',
            'SPP_APP_ID'        => $appId,
            'SPP_ROUTE_PREFIX'  => $app['route'],
            'SPP_SUBDOMAIN'     => (string)($app['subdomain'] ?? ''),
            'SPP_GATEWAY_URL'   => 'http://127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? 8000),
            'SPP_APP_TOKEN'     => $token,
            'SPP_MESH_IPC'      => self::getStorageDir() . '/mesh.sock',
        ];

        // Merge user-specified custom env
        $mergedEnv = array_merge($_ENV, getenv(), $ambientEnv, $app['env'] ?? []);

        // 5. Construct Launch Command
        $rawCmd = $app['command'] ?? null;
        if (empty($rawCmd)) {
            // Default command inferred from type
            $rawCmd = match ($appType) {
                'python'  => 'python -m uvicorn main:app --host 127.0.0.1 --port $PORT',
                'node'    => 'node server.js',
                'go'      => './main',
                'rust'    => './target/release/' . $appId,
                'php-fpm' => 'php-fpm',
                default   => 'node index.js'
            };
        }

        // Replace $PORT or %PORT% in command string
        $cmd = str_replace(['$PORT', '${PORT}', '%PORT%'], (string)$port, $rawCmd);

        // 6. Spawn Background Process
        $logFile = self::getLogDir() . '/' . $appId . '.log';
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['file', $logFile, 'a'], // stdout
            2 => ['file', $logFile, 'a'], // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $workingDir, $mergedEnv, [
            'bypass_shell' => false,
            'create_new_console' => false,
        ]);

        if (!is_resource($process)) {
            return ['success' => false, 'error' => "Failed to spawn process for '{$appId}' with command: {$cmd}"];
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $procStatus = proc_get_status($process);
        $pid = $procStatus['pid'] ?? 0;

        // On Windows, proc_get_status might return cmd.exe PID; we locate actual child if possible
        if (PHP_OS_FAMILY === 'Windows' && $pid > 0) {
            // Give 100ms for child process spawn
            usleep(100000);
        }

        // 7. Write State Ledger
        $state = [
            'app_id'     => $appId,
            'pid'        => $pid,
            'port'       => $port,
            'token'      => $token,
            'command'    => $cmd,
            'working_dir'=> $workingDir,
            'started_at' => date('Y-m-d H:i:s'),
            'status'     => 'running',
        ];
        self::saveRuntimeInfo($appId, $state);

        // 8. Healthcheck Polling
        if (!empty($app['healthcheck'])) {
            $healthy = self::pollHealthcheck((int)$port, (string)$app['healthcheck'], 5);
            if (!$healthy) {
                // Warning only: process may still be booting
                $state['status'] = 'booting';
                self::saveRuntimeInfo($appId, $state);
            }
        }

        return [
            'success' => true,
            'pid'     => $pid,
            'port'    => $port,
            'message' => "Application '{$appId}' started successfully on port {$port} (PID: {$pid}).",
        ];
    }

    /**
     * Stops an application daemon.
     */
    public static function stop(string $appId): array
    {
        $app = ForeignAppRegistry::getApp($appId);
        if ($app && !self::isDaemon($app)) {
            $appType = strtolower((string)($app['type'] ?? 'php'));
            return [
                'success' => true,
                'message' => "Application '{$appId}' ({$appType}) is request-driven and has no background daemon to stop.",
            ];
        }

        $info = self::getAppRuntimeInfo($appId);
        if (!$info) {
            return ['success' => false, 'error' => "Application '{$appId}' has no active runtime record."];
        }

        $pid = (int)($info['pid'] ?? 0);
        if ($pid > 0) {
            self::terminatePid($pid);
        }

        $stateFile = self::getStorageDir() . '/' . $appId . '.state.json';
        if (file_exists($stateFile)) {
            @unlink($stateFile);
        }

        return [
            'success' => true,
            'message' => "Application '{$appId}' (PID: {$pid}) stopped.",
        ];
    }

    /**
     * Restarts an application daemon.
     */
    public static function restart(string $appId): array
    {
        self::stop($appId);
        usleep(300000); // 300ms pause
        return self::start($appId);
    }

    /**
     * Checks if an application daemon is currently alive.
     */
    public static function isRunning(string $appId): bool
    {
        $info = self::getAppRuntimeInfo($appId);
        if (!$info || empty($info['pid'])) {
            return false;
        }

        $pid = (int)$info['pid'];
        return self::isPidAlive($pid);
    }

    /**
     * Cross-platform PID liveness check.
     */
    public static function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) return false;

        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            @exec("tasklist /FI \"PID eq {$pid}\" 2>nul", $out);
            $fullOut = implode("\n", $out);
            return str_contains($fullOut, (string)$pid);
        }

        // Linux / macOS / Unix
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        $out = [];
        @exec("kill -0 {$pid} 2>&1", $out, $ret);
        return $ret === 0;
    }

    /**
     * Cross-platform process termination.
     */
    public static function terminatePid(int $pid): void
    {
        if ($pid <= 4) return; // Prevent terminating system init/idle

        if (PHP_OS_FAMILY === 'Windows') {
            @exec("taskkill /F /T /PID {$pid} 2>nul");
        } else {
            @exec("kill -15 {$pid} 2>/dev/null");
            usleep(100000);
            if (self::isPidAlive($pid)) {
                @exec("kill -9 {$pid} 2>/dev/null");
            }
        }
    }

    /**
     * Allocates an available TCP port for the application daemon.
     */
    public static function allocatePort(array $app): int
    {
        $configuredPort = $app['port'] ?? 'auto';
        if (is_numeric($configuredPort) && (int)$configuredPort > 0) {
            return (int)$configuredPort;
        }

        // Search for an open loopback port in range 10000 - 20000
        for ($p = 10001; $p <= 19999; $p++) {
            $fp = @fsockopen('127.0.0.1', $p, $errno, $errstr, 0.1);
            if (!$fp) {
                // Port is free
                return $p;
            }
            fclose($fp);
        }

        return 10042; // Fallback
    }

    /**
     * Polls healthcheck endpoint.
     */
    public static function pollHealthcheck(int $port, string $endpoint, int $maxAttempts = 5): bool
    {
        $url = "http://127.0.0.1:{$port}/" . ltrim($endpoint, '/');

        for ($i = 0; $i < $maxAttempts; $i++) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 1,
                    'ignore_errors' => true,
                ]
            ]);
            $fp = @fopen($url, 'r', false, $ctx);
            if ($fp) {
                $meta = stream_get_meta_data($fp);
                fclose($fp);
                $statusLine = $meta['wrapper_data'][0] ?? '';
                if (str_contains($statusLine, '200') || str_contains($statusLine, 'OK')) {
                    return true;
                }
            }
            usleep(200000); // 200ms
        }

        return false;
    }

    /**
     * Reads application runtime info from disk.
     *
     * @return array<string, mixed>|null
     */
    public static function getAppRuntimeInfo(string $appId): ?array
    {
        $stateFile = self::getStorageDir() . '/' . $appId . '.state.json';
        if (!file_exists($stateFile)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($stateFile), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Persists application runtime info to disk.
     */
    public static function saveRuntimeInfo(string $appId, array $data): void
    {
        $stateFile = self::getStorageDir() . '/' . $appId . '.state.json';
        file_put_contents($stateFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Retrieves status dashboard for all registered applications.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAllStatuses(): array
    {
        $apps = ForeignAppRegistry::getAllApps();
        $statuses = [];

        foreach ($apps as $id => $app) {
            $appType = strtolower((string)($app['type'] ?? 'node'));
            // Native SPP applications are managed by the core SPP application runtime (top table)
            // and should not be managed or displayed as guest supervisor daemons/microservices.
            if ($appType === 'spp') {
                continue;
            }
            $isDaemon = self::isDaemon($app);
            $running = $isDaemon ? self::isRunning($id) : (bool)$app['enabled'];
            $runtime = self::getAppRuntimeInfo($id) ?? [];

            if (!$app['enabled']) {
                $stateLabel = 'Disabled';
            } elseif ($isDaemon) {
                $stateLabel = $running ? 'Running' : 'Stopped';
            } elseif ($appType === 'static') {
                $stateLabel = 'Ready (Static)';
            } elseif ($appType === 'php-fpm') {
                $stateLabel = 'Ready (PHP-FPM)';
            } else {
                $stateLabel = 'Ready (PHP Engine)';
            }

            $statuses[$id] = [
                'id'          => $id,
                'name'        => $app['name'],
                'type'        => $appType,
                'route'       => $app['route'],
                'subdomain'   => $app['subdomain'] ?? '-',
                'enabled'     => $app['enabled'],
                'is_daemon'   => $isDaemon,
                'running'     => $running,
                'state_label' => $stateLabel,
                'pid'         => $runtime['pid'] ?? ($isDaemon ? '-' : 'N/A'),
                'port'        => $runtime['port'] ?? ($isDaemon ? '-' : 'N/A'),
                'started_at'  => $runtime['started_at'] ?? '-',
                'status'      => !$app['enabled'] ? 'disabled' : ($running ? 'active' : 'stopped'),
            ];
        }

        return $statuses;
    }

    /**
     * Reads the tail of an application's log file.
     *
     * @param string $appId
     * @param int $lines
     * @return array<string>
     */
    public static function getLogs(string $appId, int $lines = 50): array
    {
        $logFile = self::getLogDir() . '/' . $appId . '.log';
        if (!file_exists($logFile)) {
            return ["Log file for '{$appId}' does not exist yet ({$logFile})."];
        }

        $allLines = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($allLines === false) {
            return [];
        }

        if (count($allLines) > $lines) {
            return array_slice($allLines, -$lines);
        }

        return $allLines;
    }
}
