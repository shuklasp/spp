<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class AdminFormsCommand extends Command
{
    protected string $name = 'admin:forms';
    protected string $description = 'Manage Admin Forms operations. Usage: admin:forms <action> [--payload=...] [--json]';

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
    $forms = \SPP\Scheduler::withContext($appname, function() use ($appname) {
        $formsDir = SPP_BASE_DIR . '/etc/apps/' . $appname . '/forms';
        $formMap = [];
        if (is_dir($formsDir)) {
            $files = array_merge(glob($formsDir . '/*.yml'), glob($formsDir . '/*.xml'));
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $formMap[$name] = [
                    'name' => $name,
                    'type' => strtoupper(pathinfo($file, PATHINFO_EXTENSION)),
                    'content' => file_get_contents($file)
                ];
            }
        }
        return array_values($formMap);
    });
    $this->json(['forms' => $forms], $args); return;

    }

    private function handleSave(array $payload, array $args): void {

    $name = trim($payload['name'] ?? '');
    $content = $payload['content'] ?? '';
    $appname = $payload['appname'] ?? 'default';
    $type = strtolower($payload['type'] ?? 'yml');
    
    if (empty($name) || empty($content)) {
        $this->json(['success' => false, 'error' => "Name and content required."], $args); return;
        return;
    }

    $formsDir = SPP_BASE_DIR . '/etc/apps/' . $appname . '/forms';
    if (!is_dir($formsDir)) mkdir($formsDir, 0777, true);

    $filePath = $formsDir . '/' . strtolower($name) . '.' . $type;
    file_put_contents($filePath, $content);
    $this->json(['success' => true, 'message' => "Form '$name' saved."], $args); return;

    }

    private function handleDelete(array $payload, array $args): void {

    $name = trim($payload['name'] ?? '');
    $appname = $payload['appname'] ?? 'default';
    if (empty($name)) $this->json(['success' => false, 'error' => "Name required."], $args); return;
        return;

    $formsDir = SPP_BASE_DIR . '/etc/apps/' . $appname . '/forms';
    $path = $formsDir . '/' . strtolower($name) . '.yml'; // Simple fallback
    if (file_exists($path)) {
        unlink($path);
        $this->json(['success' => true, 'message' => "Form '$name' deleted."], $args); return;
    } else {
        $this->json(['success' => false, 'error' => "Form not found."], $args); return;
    }

    }

    private function handleParseyaml(array $payload, array $args): void {

    $yaml = $payload['yaml'] ?? '';
    if (empty($yaml)) $this->json(['success' => false, 'error' => "YAML required."], $args); return;
        return;
    try {
        $this->json(['config' => \Symfony\Component\Yaml\Yaml::parse($yaml)], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Parse Error: " . $e->getMessage()], $args); return;
    }

    }

    private function handleDumpyaml(array $payload, array $args): void {

    $config = $payload['config'] ?? [];
    if (is_string($config)) $config = json_decode($config, true);
    try {
        $this->json(['yaml' => \Symfony\Component\Yaml\Yaml::dump($config, 10, 2)], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Dump Error: " . $e->getMessage()], $args); return;
    }

    }

}
