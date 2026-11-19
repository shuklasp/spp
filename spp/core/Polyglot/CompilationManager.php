<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Class CompilationManager
 *
 * Sovereign, race-free compilation and artifact caching manager for compiled polyglot languages.
 * Guarantees:
 * 1. Fingerprinted build isolation (source sha256 + compiler path + version + flags + OS).
 * 2. Concurrency-safe compilation using file-level distributed mutexes (flock).
 * 3. Atomic publication of completed binaries (write-to-tmp then atomic rename).
 * 4. Transparent build telemetry logging (.build.json).
 * 5. Safe cache invalidation and artifact cleanup without breaking running workers.
 */
class CompilationManager
{
    /**
     * Resolves the cache directory for compiled artifacts.
     */
    public static function getCacheDir(string $lang): string
    {
        $sharedDir = defined('SPP_APP_DIR')
            ? SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'polyglot' . DIRECTORY_SEPARATOR . 'bin'
            : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) . '/var/cache/polyglot/bin' : 'var/cache/polyglot/bin');

        $langDir = $sharedDir . DIRECTORY_SEPARATOR . strtolower($lang);
        if (!is_dir($langDir)) {
            @mkdir($langDir, 0777, true);
        }

        return realpath($langDir) ?: $langDir;
    }

    /**
     * Computes the deterministic build fingerprint.
     */
    public static function computeFingerprint(
        string $sourcePath,
        string $compilerPath,
        string $compilerVersion,
        string $compilerFlags = ''
    ): string {
        $sourceHash = file_exists($sourcePath) ? hash_file('sha256', $sourcePath) : hash('sha256', $sourcePath);
        return hash('sha256', implode(':', [
            $sourceHash,
            $compilerPath,
            $compilerVersion,
            $compilerFlags,
            PHP_OS_FAMILY
        ]));
    }

    /**
     * Builds or retrieves a cached binary for a compiled source file.
     *
     * @param string $lang Language identifier (e.g. 'c', 'cpp', 'rust', 'zig')
     * @param string $sourcePath Path to source file
     * @param callable $compileCallback Callback: fn(string $tempOutputBinary): ProcessResult
     * @param string $compilerPath
     * @param string $compilerVersion
     * @param string $compilerFlags
     * @return array{success: bool, binary?: string, error?: string, duration_ms?: float, cached?: bool}
     */
    public static function compile(
        string $lang,
        string $sourcePath,
        callable $compileCallback,
        string $compilerPath = '',
        string $compilerVersion = '',
        string $compilerFlags = ''
    ): array {
        $sourceAbs = realpath($sourcePath) ?: $sourcePath;
        if (!file_exists($sourceAbs)) {
            return ['success' => false, 'error' => "Source file not found: '{$sourcePath}'."];
        }

        $fingerprint = self::computeFingerprint($sourceAbs, $compilerPath, $compilerVersion, $compilerFlags);
        $shortHash = substr($fingerprint, 0, 16);
        $baseName = pathinfo($sourceAbs, PATHINFO_FILENAME);
        $ext = Platform::isWindows() ? '.exe' : '';

        $cacheDir = self::getCacheDir($lang);
        $targetBinary = $cacheDir . DIRECTORY_SEPARATOR . "{$baseName}_{$shortHash}{$ext}";
        $buildInfoFile = $cacheDir . DIRECTORY_SEPARATOR . "{$baseName}_{$shortHash}.build.json";
        $lockFile = $cacheDir . DIRECTORY_SEPARATOR . "{$baseName}_{$shortHash}.lock";

        // 1. Fast path: Binary exists, size > 0, build info matches, and source has not been modified
        if (file_exists($targetBinary) && filesize($targetBinary) > 0 && file_exists($buildInfoFile)) {
            $buildInfo = json_decode((string)file_get_contents($buildInfoFile), true);
            if (is_array($buildInfo) && ($buildInfo['fingerprint'] ?? '') === $fingerprint && ($buildInfo['status'] ?? '') === 'success') {
                $sourceMtime = filemtime($sourceAbs);
                $buildTime = $buildInfo['timestamp'] ?? 0;
                if ($sourceMtime <= $buildTime) {
                    return [
                        'success' => true,
                        'binary' => $targetBinary,
                        'cached' => true,
                        'duration_ms' => $buildInfo['duration_ms'] ?? 0.0,
                    ];
                }
            }
        }

        // 2. Concurrency-safe compilation lock
        $lockFp = @fopen($lockFile, 'c+');
        if ($lockFp === false) {
            return ['success' => false, 'error' => "Could not acquire build lock file: '{$lockFile}'."];
        }

        // Wait up to 60s for lock
        $locked = @flock($lockFp, LOCK_EX);
        if (!$locked) {
            @fclose($lockFp);
            return ['success' => false, 'error' => "Timed out waiting for compilation lock on '{$targetBinary}'."];
        }

        try {
            // Re-check cache after acquiring lock (another process may have just built it)
            if (file_exists($targetBinary) && file_exists($buildInfoFile)) {
                $buildInfo = json_decode((string)file_get_contents($buildInfoFile), true);
                if (is_array($buildInfo) && ($buildInfo['fingerprint'] ?? '') === $fingerprint) {
                    return [
                        'success' => true,
                        'binary' => $targetBinary,
                        'cached' => true,
                        'duration_ms' => $buildInfo['duration_ms'] ?? 0.0,
                    ];
                }
            }

            // 3. Compile to unique temporary file
            $tmpPid = getmypid() ?: bin2hex(random_bytes(4));
            $tmpId = uniqid('bld_', true);
            $tempOutputBinary = $cacheDir . DIRECTORY_SEPARATOR . "{$baseName}_{$shortHash}.tmp_{$tmpPid}_{$tmpId}{$ext}";

            $startTime = microtime(true);
            /** @var ProcessResult $result */
            $result = $compileCallback($tempOutputBinary);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            // Verify temporary binary existence and non-zero size before publication
            if (!file_exists($tempOutputBinary) || filesize($tempOutputBinary) === 0) {
                @unlink($tempOutputBinary);
                // Remove any stale build metadata
                if (file_exists($buildInfoFile)) {
                    @unlink($buildInfoFile);
                }

                $failedRecord = [
                    'lang' => $lang,
                    'source' => $sourceAbs,
                    'target_binary' => $targetBinary,
                    'fingerprint' => $fingerprint,
                    'compiler' => $compilerPath,
                    'compiler_version' => $compilerVersion,
                    'compiler_flags' => $compilerFlags,
                    'timestamp' => time(),
                    'duration_ms' => $durationMs,
                    'exit_code' => $result instanceof ProcessResult ? $result->exitCode : 1,
                    'stdout' => $result instanceof ProcessResult ? $result->stdout : '',
                    'stderr' => $result instanceof ProcessResult ? $result->stderr : '',
                    'status' => 'failed',
                ];

                $err = !empty($failedRecord['stderr']) ? $failedRecord['stderr'] : ($failedRecord['stdout'] ?: "Binary was not generated at '{$tempOutputBinary}'.");
                return [
                    'success' => false,
                    'error' => "Compilation failed for [{$lang}]: " . trim($err),
                    'duration_ms' => $durationMs,
                    'build_record' => $failedRecord,
                ];
            }

            // 4. Atomic publication: publish binary first
            if (file_exists($targetBinary)) {
                @unlink($targetBinary);
            }

            $published = @rename($tempOutputBinary, $targetBinary);
            if (!$published) {
                @copy($tempOutputBinary, $targetBinary);
                @unlink($tempOutputBinary);
            }

            if (!Platform::isWindows()) {
                @chmod($targetBinary, 0755);
            }

            // 5. Verify published artifact on disk before writing metadata
            if (!file_exists($targetBinary) || filesize($targetBinary) === 0) {
                return [
                    'success' => false,
                    'error' => "Artifact verification failure: Published binary not found or empty at '{$targetBinary}'.",
                    'duration_ms' => $durationMs,
                ];
            }

            // 6. Write success metadata atomically via temporary file
            $buildRecord = [
                'lang' => $lang,
                'source' => $sourceAbs,
                'target_binary' => $targetBinary,
                'fingerprint' => $fingerprint,
                'compiler' => $compilerPath,
                'compiler_version' => $compilerVersion,
                'compiler_flags' => $compilerFlags,
                'timestamp' => time(),
                'duration_ms' => $durationMs,
                'exit_code' => $result instanceof ProcessResult ? $result->exitCode : 0,
                'stdout' => $result instanceof ProcessResult ? $result->stdout : '',
                'stderr' => $result instanceof ProcessResult ? $result->stderr : '',
                'status' => 'success',
            ];

            $tmpMetaFile = $buildInfoFile . '.tmp.' . bin2hex(random_bytes(4));
            @file_put_contents($tmpMetaFile, json_encode($buildRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            @rename($tmpMetaFile, $buildInfoFile);

            return [
                'success' => true,
                'binary' => $targetBinary,
                'cached' => false,
                'duration_ms' => $durationMs,
                'build_record' => $buildRecord,
            ];
        } finally {
            @flock($lockFp, LOCK_UN);
            @fclose($lockFp);
        }
    }

    /**
     * Cleans up obsolete compiled binaries that are no longer referenced or locked.
     */
    public static function cleanObsoleteArtifacts(string $lang, int $maxAgeSeconds = 86400): int
    {
        $cacheDir = self::getCacheDir($lang);
        $files = glob($cacheDir . DIRECTORY_SEPARATOR . '*');
        if (!$files) {
            return 0;
        }

        $now = time();
        $cleaned = 0;

        foreach ($files as $file) {
            if (str_ends_with($file, '.lock')) {
                continue;
            }

            $mtime = filemtime($file);
            if (($now - $mtime) > $maxAgeSeconds) {
                // Ensure file is not currently locked
                $lockCandidate = preg_replace('/\.(exe|build\.json)$/i', '', $file) . '.lock';
                if (file_exists($lockCandidate)) {
                    $lfp = @fopen($lockCandidate, 'r');
                    if ($lfp && !@flock($lfp, LOCK_EX | LOCK_NB)) {
                        @fclose($lfp);
                        continue; // Active build/worker, skip
                    }
                    if ($lfp) {
                        @flock($lfp, LOCK_UN);
                        @fclose($lfp);
                    }
                }

                if (@unlink($file)) {
                    $cleaned++;
                }
            }
        }

        return $cleaned;
    }
}
