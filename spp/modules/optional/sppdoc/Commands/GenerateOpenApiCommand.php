<?php

namespace SPPMod\SPPDoc\Commands;

use SPP\CLI\Command;
use SPPMod\SPPAPI\OpenAPI\OpenApiGenerator;

/**
 * GenerateOpenApiCommand
 * Generates automated OpenAPI 3.1 schema specifications from SPPEntity configs and Controllers.
 */
class GenerateOpenApiCommand extends Command
{
    protected string $name = 'docs:openapi:generate';
    protected string $description = 'Generate automated OpenAPI 3.1 specification schema from SPPEntity configs and Controllers';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $outputPath = 'docs/openapi.json';
        $title = 'SPP Automated API Specification';
        $version = '1.0.0';

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--output=')) {
                $outputPath = substr($arg, 9);
            } elseif (str_starts_with($arg, '--title=')) {
                $title = substr($arg, 8);
            } elseif (str_starts_with($arg, '--version=')) {
                $version = substr($arg, 10);
            }
        }

        if (!class_exists('\SPPMod\SPPAPI\OpenAPI\OpenApiGenerator')) {
            require_once dirname(__DIR__, 2) . '/sppapi/OpenAPI/OpenApiGenerator.php';
        }

        echo "\033[32mINFO:\033[0m Scanning SPPEntity configurations and Controller methods...\n";
        $json = OpenApiGenerator::generateJson($title, $version);

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        file_put_contents($outputPath, $json);
        echo "\033[32mSUCCESS:\033[0m OpenAPI 3.1 specification successfully written to `{$outputPath}`.\n";
    }
}
