<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

class DotNetBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getExecutionPlan(string $module, string $func, array $args, array $runtime, string $sharedDir): ExecutionPlan
    {
        $binary = $runtime['path'] ?? 'dotnet';
        $moduleAbs = realpath($module) ?: $module;

        $argsList = (is_dir($module) || str_ends_with($module, '.csproj'))
            ? ['run', '--project', $moduleAbs, '--', $func]
            : [$moduleAbs, $func];

        $workingDir = is_dir($sharedDir . DIRECTORY_SEPARATOR . 'bridge')
            ? $sharedDir . DIRECTORY_SEPARATOR . 'bridge'
            : (is_dir($moduleAbs) ? $moduleAbs : dirname($moduleAbs));

        return new ExecutionPlan(
            executable: $binary,
            arguments: $argsList,
            workingDirectory: $workingDir,
            environment: [
                'DOTNET_CLI_HOME' => $sharedDir . DIRECTORY_SEPARATOR . 'bridge' . DIRECTORY_SEPARATOR . '.dotnet',
                'DOTNET_NOLOGO' => '1',
                'DOTNET_SKIP_FIRST_TIME_EXPERIENCE' => '1',
            ],
            inputTransport: 'stdin_json',
            outputFormat: 'json',
            timeout: 30.0
        );
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        return $this->getExecutionPlan($module, $func, $args, $runtime, $sharedDir)->toCommandString();
    }
}
