<?php

namespace SPPMod\SPPDoc;

use SPP\Module;

class SPPDoc extends Module
{
    public function init()
    {
        if (class_exists('\SPP\CLI\CommandManager')) {
            if (!class_exists('\SPPMod\SPPDoc\Commands\GenerateOpenApiCommand')) {
                require_once __DIR__ . '/Commands/GenerateOpenApiCommand.php';
            }
            \SPP\CLI\CommandManager::registerCommand(new \SPPMod\SPPDoc\Commands\GenerateOpenApiCommand());
        }
    }

    public static function boot()
    {
        // Early boot logic
    }

    /**
     * Serve the interactive OpenAPI 3.1 schema JSON directly to web clients.
     */
    public static function serveOpenApiSchema(string $title = 'SPP Automated API Specification', string $version = '1.0.0'): void
    {
        if (!class_exists('\SPPMod\SPPAPI\OpenAPI\OpenApiGenerator')) {
            require_once dirname(__DIR__) . '/sppapi/OpenAPI/OpenApiGenerator.php';
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo \SPPMod\SPPAPI\OpenAPI\OpenApiGenerator::generateJson($title, $version);
        exit;
    }
}

