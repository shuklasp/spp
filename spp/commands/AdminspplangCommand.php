<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminspplangCommand extends Command
{
    protected string $name = 'admin:spplang';
    protected string $description = 'Manage Admin spplang operations. Usage: admin:spplang <action> [--payload=...] [--json]';

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

    private function handleGet(array $payload, array $args): void {

        $filters = [
            'locale' => $payload['locale'] ?? null,
            'status' => $payload['status'] ?? null,
            'search' => $payload['search'] ?? null
        ];
        $translations = \SPPMod\SPPLang\SPPLang::getTranslations($filters);
        $this->json(['translations' => $translations], $args); return;
    
    }

    private function handleSave(array $payload, array $args): void {

        $key = $payload['key_code'] ?? '';
        $locale = $payload['locale'] ?? '';
        $translation = $payload['translation'] ?? '';
        $status = $payload['status'] ?? 'active';

        if ($key === '' || $locale === '') {
            $this->json(['success' => false, 'error' => "Key and locale are required."], $args); return;
        return;
        }

        \SPPMod\SPPLang\SPPLang::saveTranslation($key, $locale, $translation, $status);
        $this->json(['success' => true, 'message' => "Translation saved successfully."], $args); return;
    
    }

    private function handleScan(array $payload, array $args): void {

        $locale = $payload['locale'] ?? 'en';
        $dir = dirname(SPP_BASE_DIR) . '/src';
        $newlyAdded = \SPPMod\SPPLang\SPPLang::scanDirectory($dir, $locale);
        
        $count = count($newlyAdded);
        $this->json(['success' => true, 'message' => "Scan complete! Discovered {$count} new translation keys.", 'success'], $args); return;
        $this->json(['new_keys' => $newlyAdded], $args); return;
    
    }

}
