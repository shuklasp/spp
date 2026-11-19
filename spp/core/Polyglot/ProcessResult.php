<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Class ProcessResult
 *
 * Encapsulates the complete execution telemetry of a spawned process.
 */
class ProcessResult
{
    public int $exitCode;
    public string $stdout;
    public string $stderr;
    public float $durationMs;
    public bool $timedOut;
    public string $terminationReason;
    public array $command;

    public function __construct(
        int $exitCode,
        string $stdout,
        string $stderr,
        float $durationMs,
        bool $timedOut,
        string $terminationReason,
        array $command = []
    ) {
        $this->exitCode = $exitCode;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->durationMs = $durationMs;
        $this->timedOut = $timedOut;
        $this->terminationReason = $terminationReason;
        $this->command = $command;
    }

    public function isSuccess(): bool
    {
        return $this->exitCode === 0 && $this->terminationReason === 'completed';
    }

    public function getJson(): mixed
    {
        $trimmed = trim($this->stdout);
        if ($trimmed === '') {
            return null;
        }
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        return null;
    }
}
