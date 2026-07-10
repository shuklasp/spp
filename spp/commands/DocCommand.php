<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DocCommand extends Command
{
    protected string $name = 'docs:build';
    protected string $description = 'Documentation utilities (build, api, openapi, man, phpdoc).';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $command = $args[1] ?? 'docs:build';

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
        elseif ($command === 'docs:api') {
            echo "\n\033[36mGenerating standalone API documentation...\033[0m\n";
            require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/SPPRouteDocCollector.php';
            require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/SPPEntityDocCollector.php';
            require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/SPPDocGenerator.php';
            $generator = new \SPPMod\SPPDoc\SPPDocGenerator();
            $html = $generator->generate();
            $outputDir = SPP_APP_DIR . '/docs/api';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            file_put_contents($outputDir . '/index.html', $html);
            echo "\033[32mSuccessfully generated API documentation at {$outputDir}/index.html\033[0m\n";
        }
        elseif ($command === 'docs:openapi') {
            echo "\n\033[36mExporting OpenAPI 3.0 specification...\033[0m\n";
            require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/SPPRouteDocCollector.php';
            require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/SPPEntityDocCollector.php';
            require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/SPPDocGenerator.php';
            $generator = new \SPPMod\SPPDoc\SPPDocGenerator();
            $spec = $generator->exportOpenAPI();
            $outputDir = SPP_APP_DIR . '/docs/api';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            file_put_contents($outputDir . '/openapi.json', json_encode($spec, JSON_PRETTY_PRINT));
            echo "\033[32mSuccessfully exported OpenAPI specification at {$outputDir}/openapi.json\033[0m\n";
        }
        elseif ($command === 'docs:man') {
            echo "\n\033[36mGenerating CLI manual pages...\033[0m\n";
            require_once SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/ManPageGenerator.php';
            $manDir = SPP_APP_DIR . '/man/man1';
            $docsDir = SPP_APP_DIR . '/docs/commands';
            $manualIndexPath = SPP_APP_DIR . '/docs/spp-cli-manual.md';
            \SPPMod\SPPDoc\ManPageGenerator::generate($manDir, $docsDir, $manualIndexPath);
            echo "\033[32mSuccessfully generated CLI manual pages.\033[0m\n";
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
