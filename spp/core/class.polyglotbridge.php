<?php
declare(strict_types=1);

namespace SPP;

use SPP\Core\Polyglot\LanguageRegistry;
use SPP\Core\Polyglot\PolyglotBridgeFactory;
use SPP\Core\Polyglot\ProcessRunner;
use SPP\Core\Polyglot\WorkerManager;
use SPP\Core\Polyglot\PolyglotJob;

/**
 * Class PolyglotBridge
 *
 * Orchestrates cross-language resource sharing and remote routine execution.
 * Hardened Universal Polyglot 2.0 implementation with argument-array execution,
 * dead-lock-free stream draining, authenticated daemon workers, and managed async jobs.
 */
class PolyglotBridge extends \SPP\SPPObject
{
    /**
     * Performs runtime discovery and initializes the shared bridge environment.
     */
    public static function setup(): array
    {
        $log = [];
        $log[] = "Initializing Generic Polyglot Bridge Setup...";

        // 1. Authoritative Discovery via LanguageRegistry
        $runtimes = LanguageRegistry::discoverRuntimes(true);
        $activeCount = count(array_filter($runtimes, fn ($v) => !empty($v['path'])));
        $log[] = "Discovery complete: Found {$activeCount} active runtimes.";

        // 2. Directory Management
        $sharedDir = \SPP\Module::getConfig('shared_dir', 'bridge') ?: 'var/shared';
        if ((str_starts_with($sharedDir, '/') || str_contains($sharedDir, ':')) && !is_dir($sharedDir)) {
            $sharedDir = 'var/shared';
        }

        if (!str_starts_with($sharedDir, '/') && !str_contains($sharedDir, ':')) {
            $sharedDir = SPP_APP_DIR . SPP_DS . $sharedDir;
        }

        if (!is_dir($sharedDir)) {
            @mkdir($sharedDir, 0777, true);
        }

        $sharedDir = realpath($sharedDir) ?: $sharedDir;
        $bridgeDir = $sharedDir . SPP_DS . 'bridge';
        if (!is_dir($bridgeDir)) {
            @mkdir($bridgeDir, 0777, true);
            $log[] = "Created bridge directory: " . $bridgeDir;
        }

        // 3. Generate Dispatchers inside framework library
        $frameworkLibDir = SPP_BASE_DIR . SPP_DS . 'lib' . SPP_DS . 'polyglot';
        if (!is_dir($frameworkLibDir)) {
            @mkdir($frameworkLibDir, 0777, true);
        }

        self::generateDispatchers($frameworkLibDir);
        $log[] = "Language dispatchers updated in " . $frameworkLibDir;

        // 4. Export Configuration
        $config = self::exportConfig($sharedDir, $runtimes);
        $configPath = $sharedDir . SPP_DS . 'bridge_config.json';
        $log[] = "Bridge configuration exported to " . $configPath;

        return ['success' => true, 'log' => $log, 'config' => $config];
    }

    /**
     * Executes a shell command safely, guarding against disable_functions in php.ini.
     */
    public static function safeExec(string $cmd): ?string
    {
        if (!class_exists('\\SPP\\Core\\Polyglot\\ProcessRunner') || !ProcessRunner::isSupported()) {
            return null;
        }
        $result = ProcessRunner::run($cmd);
        return $result->isSuccess() ? $result->stdout : null;
    }

    /**
     * Discovers active runtimes. Single source of truth is LanguageRegistry.
     */
    public static function discoverRuntimes(): array
    {
        if (class_exists('\\SPP\\Core\\Polyglot\\LanguageRegistry')) {
            return LanguageRegistry::discoverRuntimes();
        }

        return [
            'python'   => ['name' => 'Python', 'path' => '', 'version' => ''],
            'perl'     => ['name' => 'Perl',   'path' => '', 'version' => ''],
            'java'     => ['name' => 'Java',   'path' => '', 'version' => ''],
            'node'     => ['name' => 'Node.js', 'path' => '', 'version' => ''],
            'dotnet'   => ['name' => '.NET',   'path' => '', 'version' => ''],
            'go'       => ['name' => 'Go',     'path' => '', 'version' => ''],
            'compiler' => ['name' => 'C++ Compiler', 'path' => '', 'version' => '']
        ];
    }

    /**
     * Dispatches a managed asynchronous polyglot job.
     *
     * @return string Job ID
     */
    public static function dispatchAsync(
        string $lang,
        string $module,
        string $func,
        array $args = [],
        bool $daemon = false,
        int $timeout = 60,
        int $retries = 3,
        ?string $correlationId = null
    ): string {
        return PolyglotJob::dispatch($lang, $module, $func, $args, $daemon, $timeout, $retries, $correlationId);
    }

    /**
     * Retrieves the status ledger of a managed asynchronous job.
     */
    public static function getAsyncStatus(string $jobId): ?array
    {
        return PolyglotJob::getState($jobId);
    }

    /**
     * Retrieves the completed result of an asynchronous job.
     */
    public static function getAsyncResult(string $jobId): mixed
    {
        $state = PolyglotJob::getState($jobId);
        if (!$state) {
            return null;
        }
        return $state['result'] ?? null;
    }

    /**
     * Retrieves the error message if an asynchronous job failed.
     */
    public static function getAsyncError(string $jobId): ?string
    {
        $state = PolyglotJob::getState($jobId);
        return $state ? ($state['error'] ?? null) : null;
    }

    /**
     * Cancels a queued asynchronous job.
     */
    public static function cancelAsync(string $jobId): bool
    {
        return PolyglotJob::cancel($jobId);
    }

    /**
     * Dispatch the method call asynchronously (backward compatible fire-and-forget).
     */
    public static function callAsync(string $lang, string $module, string $func, array $args = [], bool $daemon = false): void
    {
        // Route through managed PolyglotJob for observability and status tracking
        $jobId = self::dispatchAsync($lang, $module, $func, $args, $daemon);

        // Trigger execution immediately in background without shell command chaining
        $sppCli = realpath(SPP_BASE_DIR . '/spp.php') ?: (SPP_BASE_DIR . '/spp.php');
        $phpBinary = PHP_BINARY;

        $descriptors = [
            0 => ['file', Platform::isWindows() ? 'NUL' : '/dev/null', 'r'],
            1 => ['file', Platform::isWindows() ? 'NUL' : '/dev/null', 'w'],
            2 => ['file', Platform::isWindows() ? 'NUL' : '/dev/null', 'w'],
        ];

        $command = [$phpBinary, $sppCli, 'polyglot:async', $jobId];
        $process = @proc_open($command, $descriptors, $pipes, SPP_BASE_DIR, null, [
            'bypass_shell' => true,
        ]);

        if (is_resource($process)) {
            @proc_close($process);
        }
    }

    /**
     * Direct invocation of external routines.
     *
     * @param string $lang Language identifier or extension
     * @param string $module Module file path
     * @param string $func Function / method name
     * @param array $args Arguments passed to the routine
     * @param bool $daemon Whether to execute via persistent worker daemon
     * @return array{success: bool, data?: mixed, error?: string}
     */
    public static function call(string $lang, string $module, string $func, array $args = [], bool $daemon = false): array
    {
        $lang = strtolower($lang);

        // 1. DSL & In-Process Evaluation
        if (class_exists('\\SPP\\Core\\Polyglot\\LanguageRegistry')) {
            $driver = LanguageRegistry::getDriver($lang);
            if ($driver && ($driver['category'] ?? '') === 'dsl') {
                $engineClass = $driver['dsl_engine'] ?? '\\SPP\\Core\\Polyglot\\DSL\\FormulaLanguage';
                if (class_exists($engineClass)) {
                    $engine = new $engineClass();
                    try {
                        $dslResult = $engine->executeFile($module, $args);
                        return ['success' => true, 'data' => $dslResult];
                    } catch (\Throwable $e) {
                        return ['success' => false, 'error' => "DSL Execution Error: " . $e->getMessage()];
                    }
                }
            }
        }

        // 2. Persistent Worker Daemon Mode
        if ($daemon) {
            return WorkerManager::callWorker($module, $func, $args, $lang);
        }

        // 3. Ephemeral Execution via ProcessRunner & ExecutionPlan
        $sharedDir = \SPP\Module::getConfig('shared_dir', 'bridge') ?: 'var/shared';
        if ((str_starts_with($sharedDir, '/') || str_contains($sharedDir, ':')) && !is_dir($sharedDir)) {
            $sharedDir = 'var/shared';
        }
        if (!str_starts_with($sharedDir, '/') && !str_contains($sharedDir, ':')) {
            $sharedDir = SPP_APP_DIR . SPP_DS . $sharedDir;
        }
        if (!is_dir($sharedDir)) {
            @mkdir($sharedDir, 0777, true);
        }
        $sharedDir = realpath($sharedDir) ?: $sharedDir;

        $runtimes = self::discoverRuntimes();
        $runtimeInfo = $runtimes[$lang] ?? ['name' => $lang, 'path' => ''];
        $binary = $runtimeInfo['path'] ?? '';

        if (empty($binary) && $lang !== 'compiler') {
            return ['success' => false, 'error' => "Runtime for {$lang} not discovered."];
        }

        try {
            $bridge = PolyglotBridgeFactory::getBridge($lang);

            // Prepare phase (compilation, setup)
            $prep = $bridge->prepare($module, $func, $args, $runtimeInfo, $sharedDir);
            if ($prep !== null && isset($prep['success']) && !$prep['success']) {
                return $prep;
            }

            // Get structured ExecutionPlan
            $plan = $bridge->getExecutionPlan($module, $func, $args, $runtimeInfo, $sharedDir);

            // Stream arguments via stdin as JSON
            $inputJson = json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $result = ProcessRunner::executePlan($plan, $inputJson);

            if (!$result->isSuccess()) {
                $err = !empty($result->stderr) ? $result->stderr : (!empty($result->stdout) ? $result->stdout : "Termination: {$result->terminationReason}");
                return [
                    'success' => false,
                    'error' => "Runtime Error [{$result->exitCode}]: " . trim($err),
                ];
            }

            $decoded = $result->getJson();
            if ($decoded !== null) {
                return ['success' => true, 'data' => $decoded];
            }

            // Return trimmed stdout if output was raw string / HTML / text
            return ['success' => true, 'data' => trim($result->stdout)];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => "Bridge Exception: " . $e->getMessage()];
        }
    }

    private static function generateDispatchers(string $bridgeDir): void
    {
        $writeIfDifferent = function ($file, $content) {
            if (!file_exists($file) || file_get_contents($file) !== $content) {
                file_put_contents($file, $content);
            }
        };

        // Note: The canonical dispatch scripts reside in spp/lib/polyglot/
        // generateDispatchers ensures they exist in $bridgeDir if missing.
        $srcDir = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'polyglot';
        foreach (['dispatch.py', 'dispatch.js', 'dispatch.pl'] as $script) {
            $src = $srcDir . DIRECTORY_SEPARATOR . $script;
            $dst = $bridgeDir . DIRECTORY_SEPARATOR . $script;
            if (file_exists($src) && $src !== $dst) {
                $writeIfDifferent($dst, (string)file_get_contents($src));
            }
        }
    }

    private static function exportConfig(string $sharedDir, array $runtimes): array
    {
        $dbConfig = [
            'dbtype' => \SPP\Module::getConfig('dbtype', 'sppdb'),
            'dbhost' => \SPP\Module::getConfig('dbhost', 'sppdb'),
            'dbname' => \SPP\Module::getConfig('dbname', 'sppdb'),
            'dbuser' => \SPP\Module::getConfig('dbuser', 'sppdb'),
            'dbpasswd' => \SPP\Module::getConfig('dbpasswd', 'sppdb'),
        ];

        $bridgeData = [
            'timestamp' => time(),
            'spp_version' => defined('SPP_VER') ? SPP_VER : '2.0',
            'base_dir' => SPP_BASE_DIR,
            'database' => $dbConfig,
            'modules' => \SPP\Registry::get('__mods') ?: [],
            'runtimes' => $runtimes,
            'bridge_settings' => [
                'shared_dir' => $sharedDir,
            ]
        ];

        $configPath = $sharedDir . SPP_DS . 'bridge_config.json';
        $jsonStr = json_encode($bridgeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonStr === false) {
            $jsonStr = '{}';
        }

        @file_put_contents($configPath, $jsonStr);
        return $bridgeData;
    }
}
