<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevAICommand extends Command
{
    protected string $name = 'dev:ai';
    protected string $description = 'Manage Dev AI operations. Usage: admin:ai <action> [--payload=...] [--json]';

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

    private function handleGetregistry(array $payload, array $args): void {

    \SPP\Module::loadModule('sppai');
    $registry = \SPPMod\SPPAI\SPPAI::getRegistry();
    $this->json(['registry' => $registry], $args); return;

    }

    private function handlePrompt(array $payload, array $args): void {

    \SPP\Module::loadModule('sppai');
    $provider = $payload['provider'] ?? null;
    $model = $payload['model'] ?? null;
    $prompt = $payload['prompt'] ?? '';
    
    if (!$prompt) $this->json(['success' => false, 'error' => "Prompt cannot be empty."], $args); return;
        return;
    
    $ai = \SPPMod\SPPAI\SPPAI::class;
    if ($provider) $ai = $ai::using($provider);
    if ($model) $ai = $ai::withModel($model);
    
    try {
        $result = $ai::complete($prompt);
        $this->json(['response' => $result], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "AI Error: " . $e->getMessage()], $args); return;
    }

    }

}
