<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevDocsCommand extends Command
{
    protected string $name = 'dev:docs';
    protected string $description = 'Manage Dev Docs operations. Usage: admin:docs <action> [--payload=...] [--json]';

    public function isHidden(): bool { return true; }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'default';
        $payloadRaw = $this->getOption($args, 'payload', '{}');
        $payload = json_decode($payloadRaw, true) ?: [];

        $methodName = 'handle' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
        if (method_exists($this, $methodName)) {
            $this->$methodName($payload, $args);
        } else {
            $this->json(['success' => false, 'error' => "Unknown action: $action"], $args);
        }
    }

    private function handleGetCodebaseStructure(array $payload, array $args): void {

    try {
        if (!class_exists('\\SPPMod\\SPPDoc\\DocParser')) {
            $payloadarserPath = SPP_APP_DIR . '/spp/modules/spp/sppdoc/src/DocParser.php';
            if (file_exists($payloadarserPath)) {
                require_once $payloadarserPath;
            } else {
                $this->json(['success' => false, 'error' => 'sppdoc module not found.'], $args); return;
        return;
            }
        }

        $data = \SPPMod\SPPDoc\DocParser::parseCodebase();
        $this->json($data, $args); return;
    } catch (\Throwable $e) {
        file_put_contents(SPP_APP_DIR . '/tmp_docs_error.txt', $e->getMessage() . "\n" . $e->getTraceAsString());
        throw $e;
    }

    }

    private function handleGetFileContent(array $payload, array $args): void {

    try {
        $file = $payload['file'] ?? '';
        if (!$file) $this->json(['success' => false, 'error' => 'No file specified.'], $args); return;
        return;
        
        // Validate path to prevent directory traversal
        if (str_contains($file, '..')) {
            $this->json(['success' => false, 'error' => 'Invalid file path.'], $args); return;
        return;
        }

        $absolutePath = '';
        if (str_starts_with($file, 'spp/')) {
            $absolutePath = SPP_BASE_DIR . substr($file, 3);
        } else {
            $absolutePath = SPP_APP_DIR . '/' . $file;
        }

        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            $this->json(['success' => false, 'error' => 'File not found.'], $args); return;
        return;
        }

        $content = file_get_contents($absolutePath);
        $this->json(['content' => $content], $args); return;
    } catch (\Throwable $e) {
        $this->json(['success' => false, 'error' => 'Error reading file: ' . $e->getMessage()], $args); return;
        return;
    }

    }

}
