<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DocCommand extends Command
{
    protected string $name = 'docs:build';
    protected string $description = 'Documentation utilities.';

    public function execute(array $args): void
    {
        $command = $args[1] ?? '';

        if ($command === 'docs:build') {
            echo "\n\033[36mBuilding native SPP documentation...\033[0m\n";
            if (!class_exists('\\SPPMod\\SPPDoc\\StaticGenerator')) {
                require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/DocParser.php';
                require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/StaticGenerator.php';
            }
            $outputDir = SPP_APP_DIR . '/docs/sppdoc';
            \SPPMod\SPPDoc\StaticGenerator::build($outputDir);
            echo "\033[32mSuccessfully built documentation at {$outputDir}/index.html\033[0m\n";
        } 
        elseif ($command === 'docs:phpdoc') {
            echo "\n\033[36mRunning phpDocumentor.phar wrapper...\033[0m\n";
            $phar = SPP_APP_DIR . '/phpDocumentor.phar';
            if (!file_exists($phar)) {
                $this->error("phpDocumentor.phar not found in the project root.");
                return;
            }
            
            $cmd = "php " . escapeshellarg($phar);
            echo "Executing: $cmd\n";
            
            passthru($cmd, $returnVar);
            
            if ($returnVar === 0) {
                echo "\033[32mphpDocumentor generation complete! View at docs/phpdoc/index.html\033[0m\n";
            } else {
                $this->error("phpDocumentor encountered an error.");
            }
        }
    }
}
