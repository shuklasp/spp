<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

class JavaBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getExecutionPlan(string $module, string $func, array $args, array $runtime, string $sharedDir): ExecutionPlan
    {
        $binary = $runtime['path'] ?? 'java';
        $javaLib = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'java';
        $cpSep = PHP_OS_FAMILY === 'Windows' ? ';' : ':';
        $argsJson = base64_encode(json_encode($args));
        $classpath = ".{$cpSep}{$javaLib}";

        $moduleAbs = realpath($module) ?: $module;
        $workingDir = is_dir($sharedDir . DIRECTORY_SEPARATOR . 'bridge')
            ? $sharedDir . DIRECTORY_SEPARATOR . 'bridge'
            : dirname($moduleAbs);

        return new ExecutionPlan(
            executable: $binary,
            arguments: ['-cp', $classpath, $module, $func, $argsJson],
            workingDirectory: $workingDir,
            environment: [],
            inputTransport: 'arg_base64',
            outputFormat: 'json',
            timeout: 30.0
        );
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        return $this->getExecutionPlan($module, $func, $args, $runtime, $sharedDir)->toCommandString();
    }
}
