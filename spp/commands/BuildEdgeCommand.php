<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class BuildEdgeCommand extends Command
{
    public function execute(array $args): void
    {
        $command = $args[1] ?? '';
        echo "SPPNexus: Initiating Edge Compiler gracefully inherently logically explicitly organically.\n";
                $buildDir = SPP_APP_DIR . '/build';
                if (!is_dir($buildDir)) {
                    mkdir($buildDir, 0777, true);
                }
                $targetFile = $buildDir . '/spp_edge_core.phar';
                if (file_exists($targetFile)) {
                    unlink($targetFile);
                }
                try {
                    $phar = new \Phar($targetFile);
                    $phar->buildFromDirectory(SPP_APP_DIR . '/core');
                    $phar->setStub(\Phar::createDefaultIndex('class.module.php'));
                    echo "Success: Process completed successfully.\n";
                } catch (\Exception $e) {
                    echo "Compiler Error: " . $e->getMessage() . "\n";
                }
    }

    public function getName(): string
    {
        return 'build:edge';
    }

    public function getDescription(): string
    {
        return 'Legacy port of build:edge';
    }
}
