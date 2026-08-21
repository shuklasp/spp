<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevEntitiesCommand extends Command
{
    protected string $name = 'dev:entities';
    protected string $description = 'Manage Dev Entities operations. Usage: admin:entities <action> [--payload=...] [--json]';

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

    private function handleList(array $payload, array $args): void {

        $appname = $payload['appname'] ?? 'default';
        $entities = \SPP\Scheduler::withContext($appname, function () {
            return \SPPMod\SPPDB\SPPEntity::listAvailableEntities();
        });
        $this->json(['entities' => array_values($entities)], $args); return;
    
    }

    private function handleSave(array $payload, array $args): void {

        $name = trim($payload['name'] ?? '');
        $appname = $payload['appname'] ?? 'default';
        $config = $payload['config'] ?? [];

        if (empty($name) || empty($config)) {
            $this->json(['success' => false, 'error' => "Name and configuration are required."], $args); return;
        return;
        }

        try {
            \SPPMod\SPPDB\SPPEntity::saveEntityDefinition($name, $appname, $config);
            $this->json(['success' => true, 'message' => "Entity '$name' saved successfully.", "success"], $args); return;
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => "Failed: " . $e->getMessage()], $args); return;
        }
    
    }

    private function handleDelete(array $payload, array $args): void {

        $name = trim($payload['name'] ?? '');
        $appname = $payload['appname'] ?? 'default';
        if (empty($name))
            $this->json(['success' => false, 'error' => "Name required."], $args); return;
        return;

        $filePath = SPP_BASE_DIR . '/etc/apps/' . $appname . '/entities/' . strtolower($name) . '.yml';
        if (file_exists($filePath)) {
            unlink($filePath);
            $this->json(['success' => true, 'message' => "Entity '$name' deleted."], $args); return;
        } else {
            $this->json(['success' => false, 'error' => "Not found."], $args); return;
        }
    
    }

    private function handleParseyaml(array $payload, array $args): void {

        $yaml = $payload['yaml'] ?? '';
        if (empty($yaml))
            $this->json(['success' => false, 'error' => "YAML source required."], $args); return;
        return;

        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
            $this->json(['config' => $config], $args); return;
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => "Parse Error: " . $e->getMessage()], $args); return;
        }
    
    }

    private function handleDumpyaml(array $payload, array $args): void {

        $config = $payload['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);

        try {
            $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            $this->json(['yaml' => $yaml], $args); return;
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => "Dump Error: " . $e->getMessage()], $args); return;
        }
    
    }

}
