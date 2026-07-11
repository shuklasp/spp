<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPPMod\SPPOS\KernelCompiler;

class KernelCompileCommand extends Command
{
    protected string $name = 'kernel:compile';
    protected string $description = 'Compiles the WebOS Kernel into the FastCGI performance cache.';

    public function isCLIOnly(): bool 
    { 
        return true; 
    }

    public function execute(array $args): void
    {
        echo "Compiling WebOS Kernel...\n";
        
        try {
            KernelCompiler::compile();
            echo "[SUCCESS] Kernel compiled and cached for FastCGI runtime.\n";
        } catch (\Exception $e) {
            echo "[ERROR] Kernel compilation failed: " . $e->getMessage() . "\n";
        }
    }
}
