<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

/**
 * Class ExecutionPlan
 *
 * Immutable specification for executing a polyglot service routine.
 * Eliminates shell command string interpolation by providing structured
 * executable paths, argument vectors, environment definitions, and transport requirements.
 */
class ExecutionPlan
{
    public string $executable;
    public array $arguments;
    public ?string $workingDirectory;
    public array $environment;
    public string $inputTransport; // 'stdin_json', 'arg_base64', 'none'
    public string $outputFormat;   // 'json', 'raw'
    public float $timeout;
    public ?array $compilation;
    public ?array $workerRequirements;
    public int $maxOutputBytes;

    public function __construct(
        string $executable,
        array $arguments = [],
        ?string $workingDirectory = null,
        array $environment = [],
        string $inputTransport = 'stdin_json',
        string $outputFormat = 'json',
        float $timeout = 30.0,
        ?array $compilation = null,
        ?array $workerRequirements = null,
        int $maxOutputBytes = 10485760
    ) {
        $this->executable = $executable;
        $this->arguments = $arguments;
        $this->workingDirectory = $workingDirectory;
        $this->environment = $environment;
        $this->inputTransport = $inputTransport;
        $this->outputFormat = $outputFormat;
        $this->timeout = $timeout;
        $this->compilation = $compilation;
        $this->workerRequirements = $workerRequirements;
        $this->maxOutputBytes = $maxOutputBytes;
    }

    /**
     * Converts the structured execution plan to a legacy shell command string for backward compatibility.
     */
    public function toCommandString(): string
    {
        $all = array_merge([$this->executable], $this->arguments);
        return ProcessRunner::buildCommandString($all);
    }
}
