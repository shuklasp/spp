<?php
/**
 * Module Management Service Group for SPP Admin
 */

function live_List($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $modules = \SPP\Module::listAvailableModules($appname);
    $la->setData(['modules' => $modules]);
}

function live_Scan($la, $params) {
    $modname = $params['modname'] ?? '';
    if (!$modname) return $la->setStatus('error')->notify("Module name required.");
    
    $manifest = \SPP\Module::findManifestPath($modname);
    if (!$manifest) return $la->setStatus('error')->notify("Module manifest not found.");
    
    $mod = new \SPP\Module($manifest);
    $deltas = $mod->getInstallationDeltas();
    
    $la->setData(['deltas' => $deltas])->notify("Scan complete.");
}

function live_Setup($la, $params) {
    $modname = $params['modname'] ?? '';
    if (!$modname) return $la->setStatus('error')->notify("Module name required.");
    
    try {
        if (\SPP\Core\ModuleInstaller::install($modname)) {
            $la->notify("Module '$modname' setup successful.", "success");
            $la->dispatch('refresh');
        } else {
            $la->setStatus('error')->notify("Setup failed.");
        }
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Setup Error: " . $e->getMessage());
    }
}

function live_install_all_active($la, $params) {
    try {
        $results = \SPP\Core\ModuleInstaller::installAllActive();
        $successCount = 0;
        $failCount = 0;
        foreach ($results as $mod => $res) {
            if ($res['success']) $successCount++;
            else $failCount++;
        }
        $msg = "Installed $successCount modules successfully.";
        if ($failCount > 0) $msg .= " ($failCount failed).";
        
        $la->notify($msg, $failCount === 0 ? "success" : "warning");
        $la->dispatch('refresh');
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Bulk Install Error: " . $e->getMessage());
    }
}

function live_Uninstall($la, $params) {
    $modname = $params['modname'] ?? '';
    if (!$modname) return $la->setStatus('error')->notify("Module name required.");
    
    try {
        if (\SPP\Core\ModuleInstaller::uninstall($modname)) {
            $la->notify("Module '$modname' uninstalled successful.", "success");
            $la->dispatch('refresh');
        } else {
            $la->setStatus('error')->notify("Uninstall failed.");
        }
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Uninstall Error: " . $e->getMessage());
    }
}

function live_GetConfig($la, $params) {
    $modname = $params['modname'] ?? '';
    $appname = $params['appname'] ?? 'default';
    if (!$modname) return $la->setStatus('error')->notify("Module name required.");
    
    $config = \SPP\Module::getAppConfig($modname, $appname);
    $la->setData(['config' => $config]);
}

function live_SaveConfig($la, $params) {
    $modname = $params['modname'] ?? '';
    $appname = $params['appname'] ?? 'default';
    $config = $params['config'] ?? [];
    
    if (is_string($config)) $config = json_decode($config, true);
    
    $path = \SPP\Module::getExpectedConfigPath($modname, $appname);
    $path = str_replace('\\', '/', $path);

    $existing = \SPP\Module::getAppConfig($modname, $appname);
    $finalConfig = array_merge($existing, $config);

    $data = ['variables' => $finalConfig];
    $yml = \Symfony\Component\Yaml\Yaml::dump($data, 4, 4);
    
    $logFile = SPP_LOG_DIR . '/api_debug.log';
    file_put_contents($logFile, "[Modules] SaveSettings: writing to $path\n", FILE_APPEND);

    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (file_put_contents($path, $yml) !== false) {
        $la->notify("Module '$modname' config saved.", "success");
    } else {
        file_put_contents($logFile, "[Modules] SaveSettings: FAILED to write to $path\n", FILE_APPEND);
        $la->setStatus('error')->notify("Save failed: Could not write to $path");
    }
}

function live_SaveConfigRaw($la, $params) {
    $modname = $params['modname'] ?? '';
    $appname = $params['appname'] ?? 'default';
    $content = $params['content'] ?? '';
    $format = $params['format'] ?? 'yml';
    
    // Low-level write logic using canonical path
    $path = \SPP\Module::getExpectedConfigPath($modname, $appname);
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    if (file_put_contents($path, $content)) {
        $la->notify("Raw config for '$modname' saved.", "success");
    } else {
        $la->setStatus('error')->notify("Raw save failed.");
    }
}
function live_OpenSettings($la, $params) {
    file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[Modules] OpenSettings called with: " . json_encode($params) . "\n", FILE_APPEND);
    $modname = $params['modname'] ?? '';
    $appname = $params['appname'] ?? 'default';
    $publicName = $params['public_name'] ?? $modname;
    
    if (!$modname) return $la->setStatus('error')->notify("Module name required.");
    
    $manifestPath = \SPP\Module::findManifestPath($modname);
    if (!$manifestPath) return $la->setStatus('error')->notify("Module manifest not found.");
    
    $mod = new \SPP\Module($manifestPath);
    $settingsDef = $mod->getSettingsDefinition();
    $currentConfig = \SPP\Module::getAppConfig($modname, $appname);
    
    file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[Modules] OpenSettings: mod=$modname, manifest=$manifestPath, settingsCount=" . count($settingsDef) . "\n", FILE_APPEND);

    // Build the interactive form
    $form = \SPPMod\SPPView\ViewFormBuilder::fromSettings($settingsDef, $currentConfig, 'module-settings-form');
    $formHtml = $form->getHTML();
    file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[Modules] Generated HTML length: " . strlen($formHtml) . "\n", FILE_APPEND);
    
    // Build the YAML raw content
    $yamlPath = \SPP\Module::getExpectedConfigPath($modname, $appname);
    $yamlContent = file_exists($yamlPath) ? file_get_contents($yamlPath) : "";
    
    // Create the Tabbed Layout for the Modal
    $html = "
        <div class='config-path-banner' style='font-size: 0.75rem; color: #888; margin-bottom: 15px; padding: 8px 12px; background: rgba(0,0,0,0.05); border-radius: 6px; border-left: 3px solid #007bff;'>
            <span style='opacity: 0.7; text-transform: uppercase; font-weight: bold; font-size: 0.65rem; margin-right: 8px;'>Effective Config:</span> 
            <code style='color: #007bff; word-break: break-all;'>$yamlPath</code>
            <input type='hidden' id='setup-modname' value='$modname'>
            <input type='hidden' id='setup-appname' value='$appname'>
        </div>
        
        <div class='tabs spp-tabs' style='margin-bottom:15px; border-bottom:1px solid #ddd; display:flex; gap:10px;'>
            <button id='tab-interactive' class='tab-btn active' onclick=\"admin.switchSetupTab('interactive')\" style='padding:8px 15px; border:none; background:none; cursor:pointer; border-bottom:2px solid #007bff;'>Interactive</button>
            <button id='tab-yaml' class='tab-btn' onclick=\"admin.switchSetupTab('yaml')\" style='padding:8px 15px; border:none; background:none; cursor:pointer;'>YAML (Raw)</button>
        </div>
        
        <div id='setup-pane-container' class='tab-content-container' style='min-height: 400px;'>
            <div id='setup-pane-interactive' class='setup-pane active'>
                $formHtml
            </div>
            <div id='setup-pane-yaml' class='setup-pane' style='display:none;'>
                <p class='help-text' style='margin-bottom:10px; color:#666; font-size: 0.85rem;'>Directly edit the <code>config.yml</code> file for this module.</p>
                <textarea id='raw-config-editor' class='code-editor' style='width:100%; height:350px; font-family:monospace; padding:10px; border:1px solid #ccc; border-radius:4px;'>$yamlContent</textarea>
                <input type='hidden' id='raw-config-format' value='yml'>
            </div>
        </div>
    ";
    
    $la->modal("Settings: $publicName", $html, [
        ['label' => 'Cancel', 'fn' => 'close'],
        ['label' => 'Save Changes', 'type' => 'primary', 'fn' => 'save']
    ]);
    
    $la->setData([
        'modname' => $modname,
        'appname' => $appname
    ]);
}

function live_Toggle($la, $params) {
    $modname = $params['modname'] ?? ($params['name'] ?? '');
    $status = $params['status'] ?? ($params['active'] ? 'active' : 'inactive');
    $appname = $params['appname'] ?? 'default';
    
    if (!$modname) return $la->setStatus('error')->notify("Module name required.");
    
    try {
        \SPP\Core\ModuleInstaller::setModuleStatus($modname, $status);
        $la->notify("Module '$modname' status updated to $status.", "success");
        // Trigger a list refresh if the component supports it
        $la->dispatch('refresh');
    } catch (\Exception $e) {
        $la->setStatus('error')->notify($e->getMessage());
    }
}
