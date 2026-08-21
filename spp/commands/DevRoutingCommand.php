<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevRoutingCommand extends Command
{
    protected string $name = 'dev:routing';
    protected string $description = 'Manage Dev Routing operations. Usage: admin:routing <action> [--payload=...] [--json]';

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

    private function handleListpages(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
    $pages = \SPP\Scheduler::withContext($appname, function () {
        return \SPPMod\SPPView\Pages::listPages();
    });

    $sources = [];
    foreach ($pages as $p) {
        $sourceKey = $p['source'] === 'db' ? ($p['db_summary'] ?? 'Database') : ($p['source_path'] ?? 'pages.yml');
        if (!isset($sources[$sourceKey])) {
            $sources[$sourceKey] = [
                'label' => $sourceKey,
                'type' => $p['source'],
                'items' => []
            ];
        }
        $sources[$sourceKey]['items'][] = $p;
    }

    $this->json(['sources' => array_values($sources)], $args); return;

    }

    private function handleSavepage(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
    $name = $payload['name'] ?? '';
    $url = $payload['url'] ?? '';
    $source = $payload['source'] ?? 'yaml';

    if (empty($name) || empty($url)) {
        $this->json(['success' => false, 'error' => "Name and URL are required."], $args); return;
        return;
    }

    \SPP\Scheduler::withContext($appname, function () use ($name, $url, $source) {
        \SPPMod\SPPView\Pages::savePage($name, $url, $source);
    });
    $this->json(['success' => true, 'message' => "Page route '$name' saved successfully.", "success"], $args); return;

    }

    private function handleRemovepage(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
    $name = $payload['name'] ?? '';
    $source = $payload['source'] ?? 'yaml';

    if (empty($name))
        $this->json(['success' => false, 'error' => "Name required."], $args); return;
        return;

    \SPP\Scheduler::withContext($appname, function () use ($name, $source) {
        \SPPMod\SPPView\Pages::removePage($name, $source);
    });
    $this->json(['success' => true, 'message' => "Page route '$name' removed."], $args); return;

    }

    private function handleListservices(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
    $services = \SPP\Scheduler::withContext($appname, function () {
        return \SPPMod\SPPAPI\SPPAjax::listServices();
    });

    $sources = [];
    foreach ($services as $s) {
        $sourceKey = $s['source'] === 'db' ? ($s['db_summary'] ?? 'Database') : ($s['source_path'] ?? 'services.yml');
        if (!isset($sources[$sourceKey])) {
            $sources[$sourceKey] = [
                'label' => $sourceKey,
                'type' => $s['source'],
                'items' => []
            ];
        }
        $sources[$sourceKey]['items'][] = $s;
    }

    $this->json(['sources' => array_values($sources)], $args); return;

    }

    private function handleSaveservice(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
    $name = $payload['name'] ?? '';
    $script = $payload['script'] ?? '';
    $method = $payload['method'] ?? 'POST';
    $source = $payload['source'] ?? 'yaml';

    if (empty($name) || empty($script)) {
        $this->json(['success' => false, 'error' => "Name and script are required."], $args); return;
        return;
    }

    \SPP\Scheduler::withContext($appname, function () use ($name, $script, $method, $source) {
        \SPPMod\SPPAPI\SPPAjax::registerService($name, $script, $method, $source);
    });
    $this->json(['success' => true, 'message' => "Service '$name' registered successfully.", "success"], $args); return;

    }

    private function handleRemoveservice(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
    $name = $payload['name'] ?? '';
    $source = $payload['source'] ?? 'yaml';

    if (empty($name))
        $this->json(['success' => false, 'error' => "Name required."], $args); return;
        return;

    \SPP\Scheduler::withContext($appname, function () use ($name, $source) {
        \SPPMod\SPPAPI\SPPAjax::unregisterService($name, $source);
    });
    $this->json(['success' => true, 'message' => "Service '$name' removed."], $args); return;

    }

}
