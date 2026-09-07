<?php
/**
 * Form Management Service Group for SPP Admin
 */

function live_Forms_List($la, $params) {
    $appname = $params['appname'] ?? 'default';
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
    $la->setData(['forms' => $forms]);
}

function live_Forms_Save($la, $params) {
    $name = trim($params['name'] ?? '');
    $content = $params['content'] ?? '';
    $appname = $params['appname'] ?? 'default';
    $type = strtolower($params['type'] ?? 'yml');
    
    if (empty($name) || empty($content)) {
        return $la->setStatus('error')->notify("Name and content required.");
    }

    $formsDir = SPP_BASE_DIR . '/etc/apps/' . $appname . '/forms';
    if (!is_dir($formsDir)) mkdir($formsDir, 0777, true);

    $filePath = $formsDir . '/' . strtolower($name) . '.' . $type;
    file_put_contents($filePath, $content);
    $la->notify("Form '$name' saved.");
}

function live_Forms_Delete($la, $params) {
    $name = trim($params['name'] ?? '');
    $appname = $params['appname'] ?? 'default';
    if (empty($name)) return $la->setStatus('error')->notify("Name required.");

    $formsDir = SPP_BASE_DIR . '/etc/apps/' . $appname . '/forms';
    $path = $formsDir . '/' . strtolower($name) . '.yml'; // Simple fallback
    if (file_exists($path)) {
        unlink($path);
        $la->notify("Form '$name' deleted.");
    } else {
        $la->setStatus('error')->notify("Form not found.");
    }
}

function live_Forms_ParseYAML($la, $params) {
    $yaml = $params['yaml'] ?? '';
    if (empty($yaml)) return $la->setStatus('error')->notify("YAML required.");
    try {
        $la->setData(['config' => \Symfony\Component\Yaml\Yaml::parse($yaml)]);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Parse Error: " . $e->getMessage());
    }
}

function live_Forms_DumpYAML($la, $params) {
    $config = $params['config'] ?? [];
    if (is_string($config)) $config = json_decode($config, true);
    try {
        $la->setData(['yaml' => \Symfony\Component\Yaml\Yaml::dump($config, 10, 2)]);
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Dump Error: " . $e->getMessage());
    }
}
