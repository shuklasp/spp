<?php
/**
 * Entity Management Service Group for SPP Admin
 */

if (!function_exists('live_Entities_List')) {
    function live_Entities_List($la, $params)
    {
        $appname = $params['appname'] ?? 'default';
        $entities = \SPP\Scheduler::withContext($appname, function () {
            return \SPPMod\SPPDB\SPPEntity::listAvailableEntities();
        });
        $la->setData(['entities' => array_values($entities)]);
    }
}

if (!function_exists('live_Entities_Save')) {
    function live_Entities_Save($la, $params)
    {
        $name = trim($params['name'] ?? '');
        $appname = $params['appname'] ?? 'default';
        $config = $params['config'] ?? [];

        if (empty($name) || empty($config)) {
            return $la->setStatus('error')->notify("Name and configuration are required.");
        }

        try {
            \SPPMod\SPPDB\SPPEntity::saveEntityDefinition($name, $appname, $config);
            $la->notify("Entity '$name' saved successfully.", "success");
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_Entities_Delete')) {
    function live_Entities_Delete($la, $params)
    {
        $name = trim($params['name'] ?? '');
        $appname = $params['appname'] ?? 'default';
        if (empty($name))
            return $la->setStatus('error')->notify("Name required.");

        $filePath = SPP_BASE_DIR . '/etc/apps/' . $appname . '/entities/' . strtolower($name) . '.yml';
        if (file_exists($filePath)) {
            unlink($filePath);
            $la->notify("Entity '$name' deleted.");
        } else {
            $la->setStatus('error')->notify("Not found.");
        }
    }
}

if (!function_exists('live_Entities_ParseYAML')) {
    function live_Entities_ParseYAML($la, $params)
    {
        $yaml = $params['yaml'] ?? '';
        if (empty($yaml))
            return $la->setStatus('error')->notify("YAML source required.");

        try {
            $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
            $la->setData(['config' => $config]);
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Parse Error: " . $e->getMessage());
        }
    }
}

if (!function_exists('live_Entities_DumpYAML')) {
    function live_Entities_DumpYAML($la, $params)
    {
        $config = $params['config'] ?? [];
        if (is_string($config))
            $config = json_decode($config, true);

        try {
            $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 10, 2);
            $la->setData(['yaml' => $yaml]);
        } catch (\Exception $e) {
            $la->setStatus('error')->notify("Dump Error: " . $e->getMessage());
        }
    }
}
