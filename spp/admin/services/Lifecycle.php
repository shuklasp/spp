<?php
/**
 * Lifecycle & Sync Service Group for SPP Admin
 */

function live_lifecycle_receive($la, $params) {
    // Note: getDeploymentToken and absolutizePath are defined in api.php 
    // or should be moved to a shared library. For now, we assume they are accessible.
    $token = $params['spp_deploy_token'] ?? '';
    $expectedToken = getDeploymentToken();
    if (!$expectedToken || $expectedToken === 'DISABLED' || !$token || !hash_equals($expectedToken, $token)) {
        return $la->setStatus('error')->notify("Unauthorized sync attempt.");
    }

    $type = $params['type'] ?? '';
    $payload = $params['payload'] ?? [];
    
    if ($type === 'file') {
        $path = $params['path'] ?? '';
        if (!isSafeLifecyclePath($path)) {
            return $la->setStatus('error')->notify("Unsafe deployment path.");
        }
        $absPath = absolutizePath($path);
        $dir = dirname($absPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($absPath, base64_decode($payload['content']));
    }
    $la->notify("Successfully received {$type} update.");
}

function isSafeLifecyclePath(string $path): bool {
    $normalized = str_replace('\\', '/', $path);
    if ($normalized === '' || preg_match('/^([a-zA-Z]:|\/)/', $normalized)) {
        return false;
    }
    if (str_contains($normalized, '../') || str_contains($normalized, '/..') || $normalized === '..') {
        return false;
    }
    return true;
}

function live_lifecycle_backup($la, $params) {
    $filename = 'spp_backup_' . date('Ymd_His') . '.zip';
    $path = absolutizePath('var/backups/' . $filename);
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);

    if (\SPPMod\SPPSync\SPPSync::createBackup($path)) {
        $la->setData(['filename' => $filename])->notify("Backup created successfully.");
    } else {
        $la->setStatus('error')->notify("Failed to create backup.");
    }
}

function live_Lifecycle_GetEnvs($la, $params) {
    $syncConfig = getSyncConfig();
    $la->setData(['environments' => $syncConfig['environments'] ?? []]);
}

function live_Lifecycle_GetSecurity($la, $params) {
    $la->setData(['token' => getDeploymentToken()]);
}

function live_lifecycle_rotatetoken($la, $params) {
    $token = bin2hex(random_bytes(16));
    if (setDeploymentToken($token)) {
        $la->setData(['token' => $token])->notify("Security token rotated.");
    } else {
        $la->setStatus('error')->notify("Failed to update token.");
    }
}

function live_lifecycle_updatelist($la, $params) {
    error_log("[Lifecycle] UpdateList triggered");
    $appname = $params['appname'] ?? 'default';
    $updates = [];
    $modules = \SPP\Module::listAvailableModules($appname);
    error_log("[Lifecycle] Modules found: " . count($modules));
    
    foreach ($modules as $mod) {
        $manifest = \SPP\Module::findManifestPath($mod['name'], $appname);
        if (!$manifest) continue;
        $m = new \SPP\Module($manifest, $appname);
        $deltas = $m->getInstallationDeltas();
        error_log("[Lifecycle] Module: {$mod['name']}, Deltas: " . count($deltas));
        if (!empty($deltas)) {
             $updates[] = ['module' => $mod['name'], 'deltas' => $deltas];
        }
    }
    
    if (empty($updates)) {
        $la->modal("System Up to Date", '
            <div style="padding: 2rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h3>All systems synchronized</h3>
                <p style="opacity: 0.6;">No pending schema or manifest updates found.</p>
            </div>
        ', [['label' => 'Dismiss', 'type' => 'secondary', 'fn' => 'close']]);
    } else {
        $listHtml = '<ul class="update-list">';
        foreach ($updates as $u) {
            $listHtml .= "<li><strong>{$u['module']}</strong>: " . count($u['deltas']) . " pending changes</li>";
        }
        $listHtml .= '</ul>';
        
        $html = "
            <div style='padding: 1rem;'>
                <p>Found " . count($updates) . " modules with pending updates:</p>
                $listHtml
            </div>";
            
        $la->modal("Pending Updates Found", $html, [
            ['label' => 'Cancel', 'type' => 'secondary', 'fn' => 'close'],
            ['label' => 'Apply Updates', 'type' => 'primary', 'fn' => 'admin.applySystemUpdate()']
        ]);
    }
}

function live_lifecycle_updaterun($la, $params) {
    $appname = $params['appname'] ?? 'default';
    $modules = \SPP\Module::listAvailableModules($appname);
    error_log("[Lifecycle] Modules found: " . count($modules));
    
    foreach ($modules as $mod) {
        $manifest = \SPP\Module::findManifestPath($mod['name'], $appname);
        if (!$manifest) continue;
        $m = new \SPP\Module($manifest, $appname);
        if ($m->install()) $successCount++;
    }
    
    $la->modal("Update Complete", "
        <div style='padding: 2rem; text-align: center;'>
            <div style='font-size: 3rem; margin-bottom: 1rem;'>🚀</div>
            <h3>System Successfully Updated</h3>
            <p style='opacity: 0.6;'>Processed $successCount modules.</p>
        </div>
    ", [['label' => 'Finish', 'type' => 'primary', 'fn' => 'close']]);
}
function live_lifecycle_config_target($la, $params) {
    $path = SPP_BASE_DIR . '/modules/spp/sppsync/config.yml';
    $content = file_exists($path) ? file_get_contents($path) : "environments:\n  production:\n    url: ''\n    token: ''";
    
    $html = '
        <div class="form-group">
            <label style="display:block; margin-bottom: 10px; font-weight: 600;">Deployment Environments (YAML)</label>
            <textarea id="sync-config-raw" class="form-control" style="font-family: \'JetBrains Mono\', monospace; height: 350px; width: 100%; background: rgba(0,0,0,0.2); color: #e2e8f0; padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border);">' . htmlspecialchars($content) . '</textarea>
            <div style="margin-top: 10px; font-size: 0.8rem; opacity: 0.6;">
                ⚠️ Modifying this file will update the remote synchronization targets for all applications.
            </div>
        </div>
    ';
    
    $la->modal("Configure Deployment Targets", $html, [
        ['label' => 'Cancel', 'type' => 'secondary', 'fn' => 'close'],
        ['label' => 'Save Configuration', 'type' => 'primary', 'fn' => 'admin.saveSyncConfig()']
    ]);
}

function live_lifecycle_save_target($la, $params) {
    $yaml = $params['yaml'] ?? '';
    if (!$yaml) return $la->setStatus('error')->notify("Configuration cannot be empty.");
    
    try {
        // Validate YAML
        $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
        if (!isset($parsed['environments'])) throw new \Exception("Missing 'environments' root key.");
        
        $path = SPP_BASE_DIR . '/modules/spp/sppsync/config.yml';
        if (file_put_contents($path, $yaml) !== false) {
            $la->notify("Deployment targets updated successfully.")->closeModal()->refresh();
        } else {
            throw new \Exception("Failed to write to config.yml");
        }
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Invalid YAML: " . $e->getMessage());
    }
}

function live_sys_upgrade($la, $params) {
    if (!\SPP\Module::isEnabled('sppdb')) {
        return $la->setStatus('error')->notify("Error: sppdb module is not enabled. Upgrades cannot run.");
    }

    try {
        // Ensure system tables first
        \SPP\Core\ModuleInstaller::setupSystemTables();

        \SPP\Module::loadAllModules();
        $modules = \SPP\Registry::get('__mods') ?? [];
        
        $count = 0;
        foreach ($modules as $modName => $modPath) {
            $module = \SPP\Module::getModule($modName);
            if ($module) {
                $dbFile = $module->ModPath . DIRECTORY_SEPARATOR . 'db.yml';
                if (file_exists($dbFile)) {
                    \SPP\Core\ModuleInstaller::executeDbYml($module);
                    $count++;
                }
            }
        }
        $la->notify("System upgrade completed successfully. {$count} modules synchronized.");
    } catch (\Exception $e) {
        $la->setStatus('error')->notify("Upgrade Failed: " . $e->getMessage());
    }
}
