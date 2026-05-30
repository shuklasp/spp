<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class ManifestExportCommand extends Command
{
    public function execute(array $args): void
    {
        echo "🤖 Synthesizing AI Copilot Autodiscovery Manifest...\n";
        $wellKnownDir = SPP_APP_DIR . '/.well-known';
        if (!is_dir($wellKnownDir)) @mkdir($wellKnownDir, 0777, true);
        $targetManifest = $wellKnownDir . '/spp-ai-plugin.json';
        
        $manifestData = null;
        $sppaiClass = '\\SPPMod\\SPPAI\\SPPAI';
        if (class_exists($sppaiClass) && method_exists($sppaiClass, 'generateAiManifest')) {
            $manifestData = $sppaiClass::generateAiManifest();
        } else {
            $manifestData = json_encode([
                "schema_version" => "v1",
                "name_for_model" => "SPP_Enterprise_Engine",
                "description_for_model" => "Satya Portal Platform framework interface allowing external Copilot models to discover operational tools.",
                "auth" => ["type" => "none"],
                "api" => ["type" => "openapi", "url" => "/api.php?__manifest=true"],
                "logo_url" => "/res/spp/images/logo.jpg"
            ], JSON_PRETTY_PRINT);
        }
        file_put_contents($targetManifest, $manifestData);
        echo "  📄 Successfully exported Open API plugin definition map: {$targetManifest}\n";
    }

    public function getName(): string
    {
        return 'manifest:export';
    }

    public function getDescription(): string
    {
        return 'Exports tool autodiscovery definitions for AI Copilots';
    }
}
