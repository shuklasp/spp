<?php
/**
 * Configuration Service Group for SPP Admin
 */

function live_Config_InterDB_Get($la, $params) {
    $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
    if (!file_exists($path)) {
        return $la->setData(['mode' => 'interdb', 'mappings' => []]);
    }
    $config = \Symfony\Component\Yaml\Yaml::parseFile($path);
    $la->setData($config);
}

function live_Config_InterDB_Save($la, $params) {
    $mode = $params['mode'] ?? 'interdb';
    $mappings = $params['mappings'] ?? [];
    try {
        $path = SPP_MODULES_DIR . '/spp/sppinterdb/etc/config.yml';
        $yaml = \Symfony\Component\Yaml\Yaml::dump(['mode' => $mode, 'mappings' => $mappings], 4, 4);
        file_put_contents($path, $yaml);
        $la->notify("InterDB configuration saved.", "success");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Failed to save: " . $e->getMessage());
    }
}

function live_Config_Ajax_List($la, $params) {
    $services = \SPPMod\SPPAjax\SPPAjax::listServices();
    $la->setData(['services' => $services]);
}

function live_Config_Ajax_Save($la, $params) {
    $name = $params['name'] ?? '';
    $script = $params['script'] ?? '';
    $method = $params['method'] ?? 'POST';
    $source = $params['source'] ?? 'yaml';
    
    if (empty($name) || empty($script)) {
        return $la->setStatus('error')->notify("Service name and script are required.");
    }
    
    \SPPMod\SPPAjax\SPPAjax::registerService($name, $script, $method, $source);
    $la->notify("Service '{$name}' registered successfully.", "success");
}
function live_Config_GetGlobalSettings($la, $params) {
    $path = SPP_BASE_DIR . '/etc/global-settings.yml';
    if (!file_exists($path)) {
        return $la->setStatus('error')->notify("Global settings file not found.");
    }
    $raw = file_get_contents($path);
    $parsed = \Symfony\Component\Yaml\Yaml::parse($raw);
    $la->setData([
        'raw' => $raw,
        'parsed' => $parsed
    ]);
}

function live_Config_SaveGlobalSettings($la, $params) {
    $mode = $params['mode'] ?? 'form';
    $path = SPP_BASE_DIR . '/etc/global-settings.yml';

    try {
        if ($mode === 'yaml') {
            $yaml = $params['yaml'] ?? '';
            if (empty($yaml)) return $la->setStatus('error')->notify("YAML content is empty.");
            file_put_contents($path, $yaml);
        } else {
            $data = $params['data'] ?? null;
            if (!$data) return $la->setStatus('error')->notify("No data provided.");
            if (is_string($data)) $data = json_decode($data, true);
            
            $yaml = \Symfony\Component\Yaml\Yaml::dump($data, 10, 4);
            file_put_contents($path, $yaml);
        }
        $la->notify("Global settings saved successfully.", "success");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Save failed: " . $e->getMessage());
    }
}
