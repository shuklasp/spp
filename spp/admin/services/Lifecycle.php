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

function live_lifecycle_deploy_history($la, $params) {
    $backupDir = SPP_BASE_DIR . '/var/backups';
    $history = [];
    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/deploy_backup_*.zip');
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        foreach (array_slice($files, 0, 20) as $f) {
            $history[] = [
                'filename' => basename($f),
                'size' => filesize($f),
                'date' => date('Y-m-d H:i:s', filemtime($f)),
                'timestamp' => filemtime($f)
            ];
        }
    }
    $la->setData(['history' => $history]);
}

function live_lifecycle_list_backups($la, $params) {
    $backupDir = SPP_BASE_DIR . '/var/backups';
    $backups = [];
    if (is_dir($backupDir)) {
        $files = array_merge(
            glob($backupDir . '/*.zip') ?: [],
            glob($backupDir . '/*.sql') ?: []
        );
        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
        foreach ($files as $f) {
            $backups[] = [
                'filename' => basename($f),
                'size' => filesize($f),
                'size_human' => round(filesize($f) / 1024 / 1024, 2) . ' MB',
                'date' => date('Y-m-d H:i:s', filemtime($f)),
                'type' => pathinfo($f, PATHINFO_EXTENSION)
            ];
        }
    }
    $la->setData(['backups' => $backups]);
}

function live_lifecycle_remote_logs($la, $params) {
    $target = $params['target'] ?? 'production';
    $offset = $params['offset'] ?? -1;
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        return $la->setStatus('error')->notify("Target '{$target}' not configured.");
    }
    
    $url = rtrim($env['url'], '/') . '/_sppdeploy/logs';
    $qs = [];
    if ($offset >= 0) $qs['offset'] = $offset;
    else $qs['lines'] = 100;
    if (!empty($qs)) $url .= '?' . http_build_query($qs);
    
    $token = $env['token'] ?? '';
    $signature = hash_hmac('sha256', '', $token);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Deploy-Token: ' . $token,
        'X-Signature: ' . $signature
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$resp) {
        return $la->setStatus('error')->notify("Failed to fetch remote logs (HTTP {$httpCode}).");
    }
    
    $data = json_decode($resp, true);
    $la->setData($data ?: []);
}

function live_lifecycle_remote_run($la, $params) {
    $target = $params['target'] ?? 'production';
    $command = $params['command'] ?? '';
    if (!$command) return $la->setStatus('error')->notify('Command is required.');
    
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        return $la->setStatus('error')->notify("Target '{$target}' not configured.");
    }
    
    $url = rtrim($env['url'], '/') . '/_sppdeploy/run';
    $token = $env['token'] ?? '';
    $payload = json_encode(['command' => $command]);
    $signature = hash_hmac('sha256', $payload, $token);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Deploy-Token: ' . $token,
        'X-Signature: ' . $signature
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$resp) {
        return $la->setStatus('error')->notify("Remote command failed (HTTP {$httpCode}).");
    }
    
    $data = json_decode($resp, true);
    $la->setData($data ?: []);
}

function live_lifecycle_health_check($la, $params) {
    $target = $params['target'] ?? 'production';
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        return $la->setStatus('error')->notify("Target '{$target}' not configured.");
    }
    
    $url = rtrim($env['url'], '/') . '/_sppdeploy/health';
    $token = $env['token'] ?? '';
    $signature = hash_hmac('sha256', '', $token);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Deploy-Token: ' . $token,
        'X-Signature: ' . $signature
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $latency = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
    curl_close($ch);
    
    $data = json_decode($resp, true) ?: [];
    $data['http_code'] = $httpCode;
    $data['latency_ms'] = $latency;
    $data['reachable'] = ($httpCode === 200);
    $la->setData($data);
}

function live_lifecycle_get_webhooks($la, $params) {
    $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
    $webhooks = [];
    if (file_exists($confFile)) {
        $conf = @yaml_parse_file($confFile);
        $webhooks = $conf['webhooks'] ?? [];
    }
    $la->setData(['webhooks' => $webhooks]);
}

function live_lifecycle_save_webhooks($la, $params) {
    $webhooks = $params['webhooks'] ?? [];
    $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
    $conf = [];
    if (file_exists($confFile)) {
        $conf = @yaml_parse_file($confFile) ?: [];
    }
    $conf['webhooks'] = $webhooks;
    
    $yaml = '';
    foreach ($conf as $key => $value) {
        if (is_array($value)) {
            $yaml .= "{$key}:\n";
            foreach ($value as $item) {
                $yaml .= "  - {$item}\n";
            }
        } else {
            $yaml .= "{$key}: {$value}\n";
        }
    }
    
    if (file_put_contents($confFile, $yaml) !== false) {
        $la->notify('Webhook configuration saved.');
    } else {
        $la->setStatus('error')->notify('Failed to save webhook configuration.');
    }
}

function live_lifecycle_test_webhook($la, $params) {
    $url = $params['url'] ?? '';
    if (!$url) return $la->setStatus('error')->notify('Webhook URL is required.');
    
    $isDiscord = (strpos($url, 'discord.com') !== false);
    $isSlack = (strpos($url, 'slack.com') !== false || strpos($url, 'hooks.slack.com') !== false);
    
    if ($isDiscord) {
        $payload = json_encode([
            'embeds' => [[
                'title' => '🧪 SPPDeploy Test Notification',
                'description' => 'This is a test webhook from SPPAdmin.',
                'color' => 0x6366f1
            ]]
        ]);
    } elseif ($isSlack) {
        $payload = json_encode([
            'blocks' => [[
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '🧪 *SPPDeploy Test Notification*\nThis is a test webhook from SPPAdmin.']
            ]]
        ]);
    } else {
        $payload = json_encode(['text' => 'SPPDeploy Test Notification from SPPAdmin.']);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $la->notify('Test webhook sent successfully!');
    } else {
        $la->setStatus('error')->notify("Webhook failed with HTTP {$httpCode}.");
    }
}

function live_lifecycle_cluster_status($la, $params) {
    $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
    $clusters = [];
    if (file_exists($confFile)) {
        $conf = @yaml_parse_file($confFile);
        $clusters = $conf['clusters'] ?? [];
    }
    
    $syncConfig = getSyncConfig();
    $envs = $syncConfig['environments'] ?? [];
    
    $result = [];
    foreach ($clusters as $clusterName => $targets) {
        $nodes = [];
        foreach ($targets as $targetName) {
            $env = $envs[$targetName] ?? null;
            $node = ['name' => $targetName, 'reachable' => false, 'latency_ms' => 0];
            if ($env && !empty($env['url'])) {
                $url = rtrim($env['url'], '/') . '/_sppdeploy/health';
                $token = $env['token'] ?? '';
                $signature = hash_hmac('sha256', '', $token);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'X-Deploy-Token: ' . $token,
                    'X-Signature: ' . $signature
                ]);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $latency = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
                curl_close($ch);
                $node['reachable'] = ($httpCode === 200);
                $node['latency_ms'] = $latency;
            }
            $nodes[] = $node;
        }
        $result[$clusterName] = $nodes;
    }
    $la->setData(['clusters' => $result]);
}

function live_lifecycle_maintenance_toggle($la, $params) {
    $target = $params['target'] ?? 'production';
    $enable = $params['enable'] ?? true;
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        return $la->setStatus('error')->notify("Target '{$target}' not configured.");
    }
    
    $endpoint = $enable ? '/_sppdeploy/maintenance/on' : '/_sppdeploy/maintenance/off';
    $url = rtrim($env['url'], '/') . $endpoint;
    $token = $env['token'] ?? '';
    $signature = hash_hmac('sha256', '', $token);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Deploy-Token: ' . $token,
        'X-Signature: ' . $signature
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $la->notify($enable ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.');
    } else {
        $la->setStatus('error')->notify("Failed to toggle maintenance mode (HTTP {$httpCode}).");
    }
}
