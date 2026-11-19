<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Class ProcessRunner
 *
 * Sovereign, hardened process execution engine for SPP Polyglot.
 * Guarantees:
 * 1. Argument-array execution preventing shell injection.
 * 2. Concurrent, non-blocking STDOUT and STDERR draining preventing pipe deadlocks.
 * 3. Strict execution deadlines with reliable child process tree termination.
 * 4. Configurable input and output byte limits preventing memory exhaustion.
 * 5. Complete execution telemetry (exit code, stdout, stderr, duration, termination reason).
 * 6. Graceful handling of disabled PHP process functions.
 */
class ProcessRunner
{
    public const REASON_COMPLETED = 'completed';
    public const REASON_TIMEOUT = 'timeout';
    public const REASON_OUTPUT_LIMIT = 'output_limit_exceeded';
    public const REASON_INPUT_LIMIT = 'input_limit_exceeded';
    public const REASON_KILLED = 'killed';
    public const REASON_SPAWN_FAILED = 'spawn_failed';
    public const REASON_FUNCTIONS_DISABLED = 'functions_disabled';

    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_MAX_OUTPUT_BYTES = 10485760; // 10 MB
    public const DEFAULT_MAX_INPUT_BYTES = 10485760;  // 10 MB

    /**
     * Checks if PHP process execution functions are available.
     */
    public static function isSupported(): bool
    {
        if (!function_exists('proc_open') || !function_exists('proc_close') || !function_exists('proc_get_status')) {
            return false;
        }

        $disabled = explode(',', (string)ini_get('disable_functions'));
        $disabled = array_map('trim', array_map('strtolower', $disabled));

        foreach (['proc_open', 'proc_close', 'proc_get_status'] as $fn) {
            if (in_array($fn, $disabled, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Executes a process using an argument list or execution plan.
     *
     * @param array<string>|string $command Executable and arguments array, or string command
     * @param string|null $workingDir Working directory for the process
     * @param array<string, string> $environment Environment variables
     * @param string|null $input Data to stream to stdin
     * @param float $timeout Execution timeout in seconds
     * @param int $maxOutputBytes Maximum allowed combined stdout + stderr bytes
     * @param int $maxInputBytes Maximum allowed stdin payload bytes
     * @return ProcessResult
     */
    public static function run(
        array|string $command,
        ?string $workingDir = null,
        array $environment = [],
        ?string $input = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        int $maxOutputBytes = self::DEFAULT_MAX_OUTPUT_BYTES,
        int $maxInputBytes = self::DEFAULT_MAX_INPUT_BYTES
    ): ProcessResult {
        $startTime = microtime(true);
        $commandArray = is_array($command) ? array_values($command) : self::tokenizeCommandLine($command);

        // 1. Guard against disabled PHP process functions
        if (!self::isSupported()) {
            return new ProcessResult(
                -1,
                '',
                'PHP process execution functions (proc_open) are disabled by configuration.',
                0.0,
                false,
                self::REASON_FUNCTIONS_DISABLED,
                $commandArray
            );
        }

        // 2. Validate input size
        if ($input !== null && strlen($input) > $maxInputBytes) {
            return new ProcessResult(
                -1,
                '',
                "Input payload size (" . strlen($input) . " bytes) exceeds configured limit ({$maxInputBytes} bytes).",
                0.0,
                false,
                self::REASON_INPUT_LIMIT,
                $commandArray
            );
        }

        // 3. Validate and resolve working directory
        if ($workingDir !== null) {
            if (!is_dir($workingDir)) {
                @mkdir($workingDir, 0777, true);
            }
            $resolvedCwd = realpath($workingDir);
            if ($resolvedCwd === false || !is_dir($resolvedCwd)) {
                return new ProcessResult(
                    -1,
                    '',
                    "Working directory does not exist or is inaccessible: '{$workingDir}'.",
                    0.0,
                    false,
                    self::REASON_SPAWN_FAILED,
                    $commandArray
                );
            }
            $workingDir = $resolvedCwd;
        }

        // 4. Construct sanitized environment
        $env = self::buildEnvironment($environment, $workingDir);

        // 5. Execute via Symfony Process component
        if (class_exists('\\Symfony\\Component\\Process\\Process')) {
            try {
                // Strictly shell-free execution: always use argument array
                $process = new \Symfony\Component\Process\Process($commandArray, $workingDir, $env, $input, $timeout);

                $stdout = '';
                $stderr = '';
                $terminationReason = self::REASON_COMPLETED;
                $timedOut = false;

                try {
                    $process->run(function ($type, $buffer) use (&$stdout, &$stderr, $maxOutputBytes, $process, &$terminationReason) {
                        $currentBytes = strlen($stdout) + strlen($stderr);
                        $remaining = $maxOutputBytes - $currentBytes;

                        if ($remaining <= 0) {
                            $terminationReason = self::REASON_OUTPUT_LIMIT;
                            $process->stop(0.1);
                            return;
                        }

                        if (strlen($buffer) > $remaining) {
                            $buffer = substr($buffer, 0, $remaining);
                            $terminationReason = self::REASON_OUTPUT_LIMIT;
                            $process->stop(0.1);
                        }

                        if ($type === \Symfony\Component\Process\Process::ERR) {
                            $stderr .= $buffer;
                        } else {
                            $stdout .= $buffer;
                        }
                    });
                } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
                    $timedOut = true;
                    $terminationReason = self::REASON_TIMEOUT;
                } catch (\Throwable $e) {
                    $terminationReason = self::REASON_KILLED;
                    if (empty($stderr)) {
                        $stderr = $e->getMessage();
                    }
                }

                $durationMs = round((microtime(true) - $startTime) * 1000, 2);
                $exitCode = $timedOut ? -1 : ($process->getExitCode() ?? -1);

                return new ProcessResult(
                    $exitCode,
                    $stdout,
                    $stderr,
                    $durationMs,
                    $timedOut,
                    $terminationReason,
                    $commandArray
                );
            } catch (\Throwable $e) {
                $durationMs = round((microtime(true) - $startTime) * 1000, 2);
                return new ProcessResult(
                    -1,
                    '',
                    'Process spawn exception: ' . $e->getMessage(),
                    $durationMs,
                    false,
                    self::REASON_SPAWN_FAILED,
                    $commandArray
                );
            }
        }

        // 5. Setup descriptors (Fallback for raw PHP environments without Symfony Process)
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // 6. Spawn process with strict argument array and bypass_shell => true
        $options = [
            'bypass_shell' => true,
            'suppress_errors' => true,
        ];

        if (PHP_OS_FAMILY === 'Windows' && isset($commandArray[0]) && !str_contains($commandArray[0], '\\') && !str_contains($commandArray[0], '/')) {
            $resolvedBinary = Platform::findBinary($commandArray[0]);
            if ($resolvedBinary) {
                $commandArray[0] = $resolvedBinary;
            }
        }

        $process = @proc_open($commandArray, $descriptors, $pipes, $workingDir, $env, $options);

        if (!is_resource($process)) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $lastErr = error_get_last();
            $errMsg = $lastErr['message'] ?? 'Failed to spawn process.';
            return new ProcessResult(
                -1,
                '',
                $errMsg,
                $durationMs,
                false,
                self::REASON_SPAWN_FAILED,
                $commandArray
            );
        }

        // 7. Non-blocking concurrent I/O draining
        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $inputBuffer = $input ?? '';
        $inputLength = strlen($inputBuffer);
        $inputOffset = 0;
        $stdinClosed = ($inputLength === 0);

        if ($stdinClosed) {
            fclose($pipes[0]);
            unset($pipes[0]);
        }

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $terminationReason = self::REASON_COMPLETED;
        $deadline = microtime(true) + max(0.1, $timeout);

        while (true) {
            $now = microtime(true);
            if ($now >= $deadline) {
                $timedOut = true;
                $terminationReason = self::REASON_TIMEOUT;
                break;
            }

            // Check output byte limit
            if ((strlen($stdout) + strlen($stderr)) > $maxOutputBytes) {
                $terminationReason = self::REASON_OUTPUT_LIMIT;
                break;
            }

            $read = [];
            if (isset($pipes[1])) $read[] = $pipes[1];
            if (isset($pipes[2])) $read[] = $pipes[2];

            $write = [];
            if (!$stdinClosed && isset($pipes[0])) {
                $write[] = $pipes[0];
            }

            $except = null;
            $remaining = max(0.001, $deadline - $now);
            $sec = (int)floor($remaining);
            $usec = (int)(($remaining - $sec) * 1000000);

            // Cap select wait to 100ms for responsiveness
            if ($sec > 0 || $usec > 100000) {
                $sec = 0;
                $usec = 100000;
            }

            $selectRes = @stream_select($read, $write, $except, $sec, $usec);

            // Write to stdin if writable
            if (!$stdinClosed && isset($pipes[0]) && in_array($pipes[0], $write, true)) {
                $chunk = substr($inputBuffer, $inputOffset, 8192);
                $written = @fwrite($pipes[0], $chunk);
                if ($written !== false && $written > 0) {
                    $inputOffset += $written;
                    if ($inputOffset >= $inputLength) {
                        @fclose($pipes[0]);
                        unset($pipes[0]);
                        $stdinClosed = true;
                    }
                } elseif ($written === false || feof($pipes[0])) {
                    @fclose($pipes[0]);
                    unset($pipes[0]);
                    $stdinClosed = true;
                }
            }

            // Read from stdout
            if (isset($pipes[1]) && in_array($pipes[1], $read, true)) {
                $curBytes = strlen($stdout) + strlen($stderr);
                $allowed = $maxOutputBytes - $curBytes;
                if ($allowed <= 0) {
                    $terminationReason = self::REASON_OUTPUT_LIMIT;
                    break;
                }
                $readLen = min(16384, $allowed);
                $chunk = @fread($pipes[1], $readLen);
                if ($chunk !== false && strlen($chunk) > 0) {
                    $stdout .= $chunk;
                    if ((strlen($stdout) + strlen($stderr)) >= $maxOutputBytes) {
                        $terminationReason = self::REASON_OUTPUT_LIMIT;
                        break;
                    }
                }
                if (feof($pipes[1])) {
                    @fclose($pipes[1]);
                    unset($pipes[1]);
                }
            }

            // Read from stderr
            if (isset($pipes[2]) && in_array($pipes[2], $read, true)) {
                $curBytes = strlen($stdout) + strlen($stderr);
                $allowed = $maxOutputBytes - $curBytes;
                if ($allowed <= 0) {
                    $terminationReason = self::REASON_OUTPUT_LIMIT;
                    break;
                }
                $readLen = min(16384, $allowed);
                $chunk = @fread($pipes[2], $readLen);
                if ($chunk !== false && strlen($chunk) > 0) {
                    $stderr .= $chunk;
                    if ((strlen($stdout) + strlen($stderr)) >= $maxOutputBytes) {
                        $terminationReason = self::REASON_OUTPUT_LIMIT;
                        break;
                    }
                }
                if (feof($pipes[2])) {
                    @fclose($pipes[2]);
                    unset($pipes[2]);
                }
            }

            // If stdout and stderr pipes are closed, child has finished writing
            if (!isset($pipes[1]) && !isset($pipes[2])) {
                break;
            }

            // Check if process has exited
            $status = @proc_get_status($process);
            if ($status && !$status['running']) {
                // Drain any final buffered output before terminating loop
                if (isset($pipes[1])) {
                    while (($chunk = @fread($pipes[1], 16384)) !== false && strlen($chunk) > 0) {
                        $stdout .= $chunk;
                    }
                    @fclose($pipes[1]);
                    unset($pipes[1]);
                }
                if (isset($pipes[2])) {
                    while (($chunk = @fread($pipes[2], 16384)) !== false && strlen($chunk) > 0) {
                        $stderr .= $chunk;
                    }
                    @fclose($pipes[2]);
                    unset($pipes[2]);
                }
                break;
            }
        }

        // 8. Termination if timed out or limits exceeded
        if ($terminationReason !== self::REASON_COMPLETED) {
            self::terminateChildProcess($process, $pipes);
            $exitCode = -1;
        } else {
            // Clean close of any remaining pipes
            foreach ($pipes as $p) {
                if (is_resource($p)) {
                    @fclose($p);
                }
            }
            $status = @proc_get_status($process);
            $exitCode = ($status && !$status['running']) ? $status['exitcode'] : @proc_close($process);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        return new ProcessResult(
            $exitCode,
            $stdout,
            $stderr,
            $durationMs,
            $timedOut,
            $terminationReason,
            $commandArray
        );
    }

    /**
     * Executes an ExecutionPlan.
     */
    public static function executePlan(ExecutionPlan $plan, ?string $input = null): ProcessResult
    {
        $command = array_merge([$plan->executable], $plan->arguments);
        $inputData = $input;

        if ($plan->inputTransport === 'stdin_json' && $inputData === null) {
            $inputData = '';
        }

        return self::run(
            $command,
            $plan->workingDirectory,
            $plan->environment,
            $inputData,
            $plan->timeout,
            $plan->maxOutputBytes
        );
    }

    /**
     * Reliably terminates child process and any child subprocesses.
     */
    protected static function terminateChildProcess($process, array &$pipes): void
    {
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                @fclose($p);
            }
        }

        if (!is_resource($process)) {
            return;
        }

        $status = @proc_get_status($process);
        $pid = $status['pid'] ?? 0;

        if (function_exists('proc_terminate')) {
            @proc_terminate($process);
        }

        if ($pid > 0) {
            Platform::terminatePid($pid);
        }

        @proc_close($process);
    }

    /**
     * Safely constructs an environment array with standard polyglot defaults.
     */
    protected static function buildEnvironment(array $customEnv, ?string $workingDir): array
    {
        $systemEnv = getenv();
        if (!is_array($systemEnv)) {
            $systemEnv = $_ENV ?: [];
        }

        $sharedDir = defined('SPP_APP_DIR')
            ? SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'shared'
            : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'shared' : 'var/shared');

        $bridgeDir = $sharedDir . DIRECTORY_SEPARATOR . 'bridge';

        $defaults = [
            'DOTNET_CLI_HOME' => $bridgeDir . DIRECTORY_SEPARATOR . '.dotnet',
            'GOCACHE' => $bridgeDir . DIRECTORY_SEPARATOR . '.gocache',
            'DOTNET_NOLOGO' => '1',
            'DOTNET_SKIP_FIRST_TIME_EXPERIENCE' => '1',
            'PYTHONUNBUFFERED' => '1',
        ];

        return array_merge($systemEnv, $defaults, $customEnv);
    }

    /**
     * Converts an argument array into an escaped command string.
     */
    public static function buildCommandString(array $args): string
    {
        if (empty($args)) {
            return '';
        }

        $escaped = array_map(function ($arg) {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows cmd / powershell safe quoting
                if (preg_match('/^[a-zA-Z0-9_\-\.\/\\\:]+$/', $arg)) {
                    return $arg;
                }
                return '"' . str_replace(['"', '%'], ['""', '%%'], $arg) . '"';
            }
            return escapeshellarg($arg);
        }, $args);

        return implode(' ', $escaped);
    }

    /**
     * Strictly tokenizes a command-line string into an argument array without invoking any shell.
     * Handles single and double quotes, backslash escaping, and whitespace splitting.
     *
     * @param string $command
     * @return array<string>
     */
    public static function tokenizeCommandLine(string $command): array
    {
        $command = trim($command);
        if ($command === '') {
            return [];
        }

        $tokens = [];
        $length = strlen($command);
        $cursor = 0;
        $token = '';
        $inSingle = false;
        $inDouble = false;

        while ($cursor < $length) {
            $char = $command[$cursor];

            if ($char === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $cursor++;
                continue;
            }

            if ($char === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $cursor++;
                continue;
            }

            if ($char === '\\' && $inDouble && ($cursor + 1 < $length)) {
                $next = $command[$cursor + 1];
                if ($next === '"' || $next === '\\') {
                    $token .= $next;
                    $cursor += 2;
                    continue;
                }
            }

            if (($char === ' ' || $char === "\t") && !$inSingle && !$inDouble) {
                if ($token !== '') {
                    $tokens[] = $token;
                    $token = '';
                }
                $cursor++;
                continue;
            }

            $token .= $char;
            $cursor++;
        }

        if ($token !== '') {
            $tokens[] = $token;
        }

        return $tokens;
    }
}
