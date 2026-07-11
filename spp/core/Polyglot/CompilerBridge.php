<?php

namespace SPP\Core\Polyglot;

class CompilerBridge implements PolyglotBridgeInterface
{
    private ?string $outputExe = null;

    public function prepare(string $module, string $func, array $args, array $runtime, string $sharedDir): ?array
    {
        $compiler = $runtime['path'];
        if (!$compiler) {
            return ['success' => false, 'error' => "C++ Compiler not found."];
        }

        $this->outputExe = $sharedDir . DIRECTORY_SEPARATOR . 'bridge' . DIRECTORY_SEPARATOR . 'temp_bin.exe';
        if (PHP_OS_FAMILY === 'Windows') {
            $vcvars = $runtime['vcvars'] ?? '';
            $prefix = $vcvars ? "call \"{$vcvars}\" && " : "";
            $compileCmd = "{$prefix}\"{$compiler}\" /EHsc \"{$module}\" /Fe:\"{$this->outputExe}\" 2>&1";
        } else {
            $this->outputExe = $sharedDir . DIRECTORY_SEPARATOR . 'bridge' . DIRECTORY_SEPARATOR . 'temp_bin';
            $compileCmd = "\"{$compiler}\" \"{$module}\" -o \"{$this->outputExe}\" 2>&1";
        }

        $cOut = @shell_exec($compileCmd);
        if (!file_exists($this->outputExe)) {
            return ['success' => false, 'error' => "Compilation failed: " . $cOut];
        }

        return null;
    }

    public function getCommand(string $module, string $func, array $args, array $runtime, string $sharedDir): string
    {
        return "\"{$this->outputExe}\"";
    }
}
