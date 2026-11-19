<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

class CompilerBridge implements PolyglotBridgeInterface
{
    private ?string $outputExe = null;

    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        $compiler = $runtime['path'] ?? '';
        if (!$compiler) {
            return ['success' => false, 'error' => "C++ Compiler not found."];
        }

        $compilerVersion = $runtime['version'] ?? 'Unknown';
        $vcvars = $runtime['vcvars'] ?? '';

        $buildResult = CompilationManager::compile(
            'compiler',
            $module,
            function (string $tempOutputBinary) use ($compiler, $module, $vcvars) {
                if (PHP_OS_FAMILY === 'Windows') {
                    // If vcvars is needed, execute cl via batch or directly if in PATH
                    if ($vcvars) {
                        $compileCmd = ['cmd.exe', '/c', "call \"{$vcvars}\" && \"{$compiler}\" /EHsc \"{$module}\" /Fe:\"{$tempOutputBinary}\""];
                    } else {
                        $compileCmd = [$compiler, '/EHsc', $module, "/Fe:{$tempOutputBinary}"];
                    }
                } else {
                    $compileCmd = [$compiler, $module, '-o', $tempOutputBinary];
                }

                return ProcessRunner::run($compileCmd, dirname($module), [], null, 60.0);
            },
            $compiler,
            $compilerVersion,
            $vcvars
        );

        if (!$buildResult['success']) {
            return $buildResult;
        }

        $this->outputExe = $buildResult['binary'];
        return null;
    }

    public function getExecutionPlan(string $module, string $func, array $args, array $runtime, string $sharedDir): ExecutionPlan
    {
        if (!$this->outputExe || !file_exists($this->outputExe)) {
            $prep = $this->prepare($module, $func, $args, $runtime, $sharedDir);
            if ($prep !== null && isset($prep['success']) && !$prep['success']) {
                $outputExe = $this->outputExe ?: ($module . (Platform::isWindows() ? '.exe' : ''));
                return new ExecutionPlan(
                    executable: $outputExe,
                    arguments: [$func],
                    workingDirectory: dirname(realpath($module) ?: $module),
                    environment: [],
                    inputTransport: 'stdin_json',
                    outputFormat: 'json',
                    timeout: 30.0
                );
            }
        }

        $moduleAbs = realpath($module) ?: $module;
        return new ExecutionPlan(
            executable: (string)$this->outputExe,
            arguments: [$func],
            workingDirectory: dirname($moduleAbs),
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
