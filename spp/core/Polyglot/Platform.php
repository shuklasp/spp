<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Class Platform
 *
 * Provides cross-platform OS abstraction, binary resolution, socket path normalization,
 * PID validation, process lifecycle termination, and multi-tier transport helpers.
 */
class Platform
{
    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public static function isLinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    public static function isMacOS(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    public static function isBSD(): bool
    {
        return str_contains(PHP_OS, 'BSD');
    }

    public static function detectOS(): string
    {
        if (self::isWindows()) return 'windows';
        if (self::isMacOS()) return 'darwin';
        if (self::isBSD()) return 'bsd';
        return 'linux';
    }

    /**
     * Finds an executable binary in the system PATH across any OS.
     *
     * @param string $name Binary name (e.g. 'python', 'rustc', 'cargo').
     * @return string Absolute path if found, or empty string.
     */
    public static function findBinary(string $name): string
    {
        if (self::isWindows()) {
            $cmd = "where {$name} 2>nul";
            $out = @shell_exec($cmd);
            if ($out && !str_contains($out, 'not found') && !str_contains($out, 'Could not find')) {
                $lines = array_filter(array_map('trim', explode("\n", $out)));
                return !empty($lines) ? reset($lines) : '';
            }
        } else {
            $cmd = "which {$name} 2>/dev/null";
            $out = @shell_exec($cmd);
            if ($out && !str_contains($out, 'not found')) {
                return trim($out);
            }
        }
        return '';
    }

    /**
     * Checks if a process ID is currently running.
     */
    public static function isPidAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (self::isWindows()) {
            $out = [];
            @exec("tasklist /FI \"PID eq {$pid}\" /FO CSV /NH 2>nul", $out);
            $fullOut = implode("\n", $out);
            return str_contains($fullOut, "\"{$pid}\"");
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        $out = [];
        @exec("kill -0 {$pid} 2>&1", $out, $ret);
        return $ret === 0;
    }

    /**
     * Verifies that the running process with PID matches the expected identity pattern.
     * Prevents killing unrelated processes if OS recycled the PID.
     *
     * @param int $pid
     * @param string $needle Substring expected in the process command line or executable name
     * @return bool
     */
    public static function isProcessIdentityMatching(int $pid, string $needle): bool
    {
        if (!self::isPidAlive($pid)) {
            return false;
        }

        if (self::isWindows()) {
            $out = [];
            @exec("tasklist /FI \"PID eq {$pid}\" /V /FO CSV 2>nul", $out);
            $fullOut = implode("\n", $out);
            return stripos($fullOut, $needle) !== false;
        }

        // POSIX /proc or ps
        $cmdlineFile = "/proc/{$pid}/cmdline";
        if (file_exists($cmdlineFile)) {
            $cmdline = (string)@file_get_contents($cmdlineFile);
            return stripos($cmdline, $needle) !== false;
        }

        $out = [];
        @exec("ps -p {$pid} -o args= 2>/dev/null", $out);
        $fullOut = implode("\n", $out);
        return stripos($fullOut, $needle) !== false;
    }

    /**
     * Safely terminates a process with graceful signal followed by forced termination if required.
     */
    public static function terminatePid(int $pid, int $graceMs = 500): void
    {
        if ($pid <= 4) {
            return; // Never kill System Idle/Init processes
        }

        if (self::isWindows()) {
            // On Windows console applications only terminate forcefully via /F
            @exec("taskkill /F /T /PID {$pid} 2>nul");
            return;
        }

        // POSIX
        @exec("kill -15 {$pid} 2>/dev/null");
        if ($graceMs > 0) {
            usleep($graceMs * 1000);
        }
        if (self::isPidAlive($pid)) {
            @exec("kill -9 {$pid} 2>/dev/null");
        }
    }

    /**
     * Resolves the optimal socket or named pipe path for a polyglot daemon.
     *
     * @param string $hash Unique identifier / hash for the service.
     * @return array Array with ['type' => 'pipe'|'uds'|'tcp', 'path' => string]
     */
    public static function resolveSocketPath(string $hash): array
    {
        if (self::isWindows()) {
            return [
                'type' => 'tcp',
                'port_file' => self::getDaemonsDir() . DIRECTORY_SEPARATOR . "{$hash}.port",
                'pid_file' => self::getDaemonsDir() . DIRECTORY_SEPARATOR . "{$hash}.pid",
                'meta_file' => self::getDaemonsDir() . DIRECTORY_SEPARATOR . "{$hash}.meta.json",
                'pipe_name' => "\\\\.\\pipe\\spp_polyglot_{$hash}",
            ];
        }

        $candidateDirs = ['/dev/shm', sys_get_temp_dir(), self::getDaemonsDir()];
        $socketDir = self::getDaemonsDir();
        foreach ($candidateDirs as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                $socketDir = $dir;
                break;
            }
        }

        return [
            'type' => 'uds',
            'socket' => $socketDir . DIRECTORY_SEPARATOR . "spp_{$hash}.sock",
            'port_file' => self::getDaemonsDir() . DIRECTORY_SEPARATOR . "{$hash}.port",
            'pid_file' => self::getDaemonsDir() . DIRECTORY_SEPARATOR . "{$hash}.pid",
            'meta_file' => self::getDaemonsDir() . DIRECTORY_SEPARATOR . "{$hash}.meta.json",
        ];
    }

    /**
     * Returns the shared daemon directory.
     */
    public static function getDaemonsDir(): string
    {
        $sharedDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'shared' : 'var/shared';
        $daemonsDir = $sharedDir . DIRECTORY_SEPARATOR . 'bridge' . DIRECTORY_SEPARATOR . 'daemons';
        if (!is_dir($daemonsDir)) {
            @mkdir($daemonsDir, 0777, true);
        }
        return realpath($daemonsDir) ?: $daemonsDir;
    }

    /**
     * Packs a payload into a 4-byte big-endian length-prefixed frame.
     *
     * @param string $payload
     * @return string
     */
    public static function packFrame(string $payload): string
    {
        return pack('N', strlen($payload)) . $payload;
    }

    /**
     * Unpacks a 4-byte big-endian header into integer length.
     *
     * @param string $header 4 bytes
     * @return int
     */
    public static function unpackLength(string $header): int
    {
        $res = unpack('N', $header);
        return $res[1] ?? 0;
    }
}
