<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

class DefaultBridge implements PolyglotBridgeInterface
{
    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        return null;
    }

    public function getExecutionPlan(string $module, string $func, array $args, array $runtime, string $sharedDir): ExecutionPlan
    {
        $binary = !empty($runtime['path']) ? $runtime['path'] : ($runtime['name'] ?? 'unknown');
        $lang = strtolower($runtime['name'] ?? '');
        $ext = match (true) {
            str_contains($lang, 'python') => 'py',
            str_contains($lang, 'perl')   => 'pl',
            str_contains($lang, 'node')   => 'js',
            default                       => ''
        };

        $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 2);
        $frameworkLibDir = $baseDir . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'polyglot';
        $dispatchScript = $frameworkLibDir . DIRECTORY_SEPARATOR . 'dispatch.' . $ext;

        if (!file_exists($dispatchScript)) {
            throw new \RuntimeException("Dispatcher script for {$lang} not found at '{$dispatchScript}'.");
        }

        $moduleAbs = realpath($module) ?: $module;
        $workingDir = is_dir($sharedDir . DIRECTORY_SEPARATOR . 'bridge')
            ? $sharedDir . DIRECTORY_SEPARATOR . 'bridge'
            : dirname($moduleAbs);

        return new ExecutionPlan(
            executable: $binary,
            arguments: [$dispatchScript, $moduleAbs, $func],
            workingDirectory: $workingDir,
            environment: [],
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
