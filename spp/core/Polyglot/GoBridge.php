<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

class GoBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getExecutionPlan(string $module, string $func, array $args, array $runtime, string $sharedDir): ExecutionPlan
    {
        $binary = $runtime['path'] ?? 'go';
        $moduleAbs = realpath($module) ?: $module;
        $moduleDir = dirname($moduleAbs);
        $moduleFile = basename($moduleAbs);

        return new ExecutionPlan(
            executable: $binary,
            arguments: ['run', $moduleFile, $func],
            workingDirectory: $moduleDir,
            environment: [
                'GOCACHE' => $sharedDir . DIRECTORY_SEPARATOR . 'bridge' . DIRECTORY_SEPARATOR . '.gocache',
            ],
            inputTransport: 'stdin_json',
            outputFormat: 'json',
            timeout: 30.0
        );
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        $binary = $runtime['path'] ?? 'go';
        $moduleAbs = realpath($module) ?: $module;
        $moduleDir = dirname($moduleAbs);
        $moduleFile = basename($moduleAbs);
        $cdCmd = PHP_OS_FAMILY === 'Windows' ? "cd /D \"{$moduleDir}\"" : "cd \"{$moduleDir}\"";
        return "{$cdCmd} && \"{$binary}\" run \"{$moduleFile}\" \"{$func}\"";
    }
}
