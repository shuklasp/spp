<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevConfigCommand extends Command
{
    protected string $name = 'dev:config';
    protected string $description = 'Manage Dev Config operations. Usage: admin:config <action> [--payload=...] [--json]';

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

    private function handleInterdbGet(array $payload, array $args): void {

    $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
    if (!file_exists($path)) {
        $this->json(['mode' => 'interdb', 'mappings' => []], $args); return;
        return;
    }
    $config = \Symfony\Component\Yaml\Yaml::parseFile($path);
    $this->json($config, $args); return;

    }

    private function handleInterdbSave(array $payload, array $args): void {

    $mode = $payload['mode'] ?? 'interdb';
    $mappings = $payload['mappings'] ?? [];
    try {
        $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
        $yaml = \Symfony\Component\Yaml\Yaml::dump(['mode' => $mode, 'mappings' => $mappings], 4, 4);
        file_put_contents($path, $yaml);
        $this->json(['success' => true, 'message' => "InterDB configuration saved.", "success"], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Failed to save: " . $e->getMessage()], $args); return;
    }

    }

    private function handleAjaxList(array $payload, array $args): void {

    $services = \SPPMod\SPPAPI\SPPAjax::listServices();
    $this->json(['services' => $services], $args); return;

    }

    private function handleAjaxSave(array $payload, array $args): void {

    $name = $payload['name'] ?? '';
    $script = $payload['script'] ?? '';
    $method = $payload['method'] ?? 'POST';
    $source = $payload['source'] ?? 'yaml';

    if (empty($name) || empty($script)) {
        $this->json(['success' => false, 'error' => "Service name and script are required."], $args); return;
        return;
    }

    \SPPMod\SPPAPI\SPPAjax::registerService($name, $script, $method, $source);
    $this->json(['success' => true, 'message' => "Service '{$name}' registered successfully.", "success"], $args); return;

    }

    private function handleGetglobalsettings(array $payload, array $args): void {

    $path = SPP_BASE_DIR . '/etc/global-settings.yml';
    if (!file_exists($path)) {
        $this->json(['success' => false, 'error' => "Global settings file not found."], $args); return;
        return;
    }
    $raw = file_get_contents($path);
    $parsed = \Symfony\Component\Yaml\Yaml::parse($raw);
    $this->json([
        'raw' => $raw,
        'parsed' => $parsed
    ], $args); return;

    }

    private function handleSaveglobalsettings(array $payload, array $args): void {

    $mode = $payload['mode'] ?? 'form';
    $path = SPP_BASE_DIR . '/etc/global-settings.yml';

    try {
        if ($mode === 'yaml') {
            $yaml = $payload['yaml'] ?? '';
            if (empty($yaml))
                $this->json(['success' => false, 'error' => "YAML content is empty."], $args); return;
        return;
            file_put_contents($path, $yaml);
        } else {
            $data = $payload['data'] ?? null;
            if (!$data)
                $this->json(['success' => false, 'error' => "No data provided."], $args); return;
        return;
            if (is_string($data))
                $data = json_decode($data, true);

            $yaml = \Symfony\Component\Yaml\Yaml::dump($data, 10, 4);
            file_put_contents($path, $yaml);
        }
        $this->json(['success' => true, 'message' => "Global settings saved successfully.", "success"], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Save failed: " . $e->getMessage()], $args); return;
    }

    }

}
