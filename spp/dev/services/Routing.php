<?php
/**
 * Routing Management Service Group for SPP Admin
 */

function live_Routing_ListPages($la, $params)
{
    $appname = $params['appname'] ?? 'default';
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

    $la->setData(['sources' => array_values($sources)]);
}

function live_Routing_SavePage($la, $params)
{
    $appname = $params['appname'] ?? 'default';
    $name = $params['name'] ?? '';
    $url = $params['url'] ?? '';
    $source = $params['source'] ?? 'yaml';

    if (empty($name) || empty($url)) {
        return $la->setStatus('error')->notify("Name and URL are required.");
    }

    \SPP\Scheduler::withContext($appname, function () use ($name, $url, $source) {
        \SPPMod\SPPView\Pages::savePage($name, $url, $source);
    });
    $la->notify("Page route '$name' saved successfully.", "success");
}

function live_Routing_RemovePage($la, $params)
{
    $appname = $params['appname'] ?? 'default';
    $name = $params['name'] ?? '';
    $source = $params['source'] ?? 'yaml';

    if (empty($name))
        return $la->setStatus('error')->notify("Name required.");

    \SPP\Scheduler::withContext($appname, function () use ($name, $source) {
        \SPPMod\SPPView\Pages::removePage($name, $source);
    });
    $la->notify("Page route '$name' removed.");
}

function live_Routing_ListServices($la, $params)
{
    $appname = $params['appname'] ?? 'default';
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

    $la->setData(['sources' => array_values($sources)]);
}

function live_Routing_SaveService($la, $params)
{
    $appname = $params['appname'] ?? 'default';
    $name = $params['name'] ?? '';
    $script = $params['script'] ?? '';
    $method = $params['method'] ?? 'POST';
    $source = $params['source'] ?? 'yaml';

    if (empty($name) || empty($script)) {
        return $la->setStatus('error')->notify("Name and script are required.");
    }

    \SPP\Scheduler::withContext($appname, function () use ($name, $script, $method, $source) {
        \SPPMod\SPPAPI\SPPAjax::registerService($name, $script, $method, $source);
    });
    $la->notify("Service '$name' registered successfully.", "success");
}

function live_Routing_RemoveService($la, $params)
{
    $appname = $params['appname'] ?? 'default';
    $name = $params['name'] ?? '';
    $source = $params['source'] ?? 'yaml';

    if (empty($name))
        return $la->setStatus('error')->notify("Name required.");

    \SPP\Scheduler::withContext($appname, function () use ($name, $source) {
        \SPPMod\SPPAPI\SPPAjax::unregisterService($name, $source);
    });
    $la->notify("Service '$name' removed.");
}
