<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevLifecycleCommand extends Command
{
    protected string $name = 'dev:lifecycle';
    protected string $description = 'Manage Dev Lifecycle operations. Usage: admin:lifecycle <action> [--payload=...] [--json]';

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

    private function handleReceive(array $payload, array $args): void {

    // Note: getDeploymentToken and absolutizePath are defined in api.php 
    // or should be moved to a shared library. For now, we assume they are accessible.
    $token = $payload['spp_deploy_token'] ?? '';
    $expectedToken = getDeploymentToken();
    if (!$expectedToken || $expectedToken === 'DISABLED' || !$token || !hash_equals($expectedToken, $token)) {
        $this->json(['success' => false, 'error' => "Unauthorized sync attempt."], $args); return;
        return;
    }

    $type = $payload['type'] ?? '';
    $payload = $payload['payload'] ?? [];
    
    if ($type === 'file') {
        $path = $payload['path'] ?? '';
        if (!isSafeLifecyclePath($path)) {
            $this->json(['success' => false, 'error' => "Unsafe deployment path."], $args); return;
        return;
        }
        $absPath = absolutizePath($path);
        $dir = dirname($absPath);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($absPath, base64_decode($payload['content']));
    }
    $this->json(['success' => true, 'message' => "Successfully received {$type} update."], $args); return;

    }

    private function handleBackup(array $payload, array $args): void {

    $filename = 'spp_backup_' . date('Ymd_His') . '.zip';
    $path = absolutizePath('var/backups/' . $filename);
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0777, true);

    if (\SPPMod\SPPSync\SPPSync::createBackup($path)) {
        $this->notify("Backup created successfully.", $args);
        $this->json(['filename' => $filename]); return;
    } else {
        $this->json(['success' => false, 'error' => "Failed to create backup."], $args); return;
    }

    }

    private function handleGetenvs(array $payload, array $args): void {

    $syncConfig = getSyncConfig();
    $this->json(['environments' => $syncConfig['environments'] ?? []], $args); return;

    }

    private function handleGetsecurity(array $payload, array $args): void {

    $this->json(['token' => getDeploymentToken()], $args); return;

    }

    private function handleRotatetoken(array $payload, array $args): void {

    $token = bin2hex(random_bytes(16));
    if (setDeploymentToken($token)) {
        $this->notify("Security token rotated.", $args);
        $this->json(['token' => $token]); return;
    } else {
        $this->json(['success' => false, 'error' => "Failed to update token."], $args); return;
    }

    }

    private function handleUpdatelist(array $payload, array $args): void {

    error_log("[Lifecycle] UpdateList triggered");
    $appname = $payload['appname'] ?? 'default';
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
        
        ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/devlifecycle_1.php'; $html = ob_get_clean();
            
        $la->modal("Pending Updates Found", $html, [
            ['label' => 'Cancel', 'type' => 'secondary', 'fn' => 'close'],
            ['label' => 'Apply Updates', 'type' => 'primary', 'fn' => 'admin.applySystemUpdate()']
        ]);
    }

    }

    private function handleUpdaterun(array $payload, array $args): void {

    $appname = $payload['appname'] ?? 'default';
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

    private function handleConfigTarget(array $payload, array $args): void {

    $path = SPP_BASE_DIR . '/modules/spp/sppsync/config.yml';
    $content = file_exists($path) ? file_get_contents($path) : "environments:\n  production:\n    url: ''\n    token: ''";
    
    ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/devlifecycle_2.php'; $html = ob_get_clean();
    
    $la->modal("Configure Deployment Targets", $html, [
        ['label' => 'Cancel', 'type' => 'secondary', 'fn' => 'close'],
        ['label' => 'Save Configuration', 'type' => 'primary', 'fn' => 'admin.saveSyncConfig()']
    ]);

    }

    private function handleSaveTarget(array $payload, array $args): void {

    $yaml = $payload['yaml'] ?? '';
    if (!$yaml) $this->json(['success' => false, 'error' => "Configuration cannot be empty."], $args); return;
        return;
    
    try {
        // Validate YAML
        $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
        if (!isset($parsed['environments'])) throw new \Exception("Missing 'environments' root key.");
        
        $path = SPP_BASE_DIR . '/modules/spp/sppsync/config.yml';
        if (file_put_contents($path, $yaml) !== false) {
            $this->json(['success' => true, 'message' => "Deployment targets updated successfully.", 'closeModal' => true, 'refresh' => true], $args); return;
        } else {
            throw new \Exception("Failed to write to config.yml");
        }
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Invalid YAML: " . $e->getMessage()], $args); return;
    }

    }

    private function handleSysUpgrade(array $payload, array $args): void {

    if (!\SPP\Module::isEnabled('sppdb')) {
        $this->json(['success' => false, 'error' => "Error: sppdb module is not enabled. Upgrades cannot run."], $args); return;
        return;
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
        $this->json(['success' => true, 'message' => "System upgrade completed successfully. {$count} modules synchronized."], $args); return;
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Upgrade Failed: " . $e->getMessage()], $args); return;
    }

    }

    private function handleDeployHistory(array $payload, array $args): void {

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
    $this->json(['history' => $history], $args); return;

    }

    private function handleListBackups(array $payload, array $args): void {

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
    $this->json(['backups' => $backups], $args); return;

    }

    private function handleRemoteLogs(array $payload, array $args): void {

    $target = $payload['target'] ?? 'production';
    $offset = $payload['offset'] ?? -1;
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        $this->json(['success' => false, 'error' => "Target '{$target}' not configured."], $args); return;
        return;
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
        $this->json(['success' => false, 'error' => "Failed to fetch remote logs (HTTP {$httpCode})."], $args); return;
        return;
    }
    
    $data = json_decode($resp, true);
    $this->json($data ?: [], $args); return;

    }

    private function handleRemoteRun(array $payload, array $args): void {

    $target = $payload['target'] ?? 'production';
    $command = $payload['command'] ?? '';
    if (!$command) $this->json(['success' => false, 'error' => 'Command is required.'], $args); return;
        return;
    
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        $this->json(['success' => false, 'error' => "Target '{$target}' not configured."], $args); return;
        return;
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
        $this->json(['success' => false, 'error' => "Remote command failed (HTTP {$httpCode})."], $args); return;
        return;
    }
    
    $data = json_decode($resp, true);
    $this->json($data ?: [], $args); return;

    }

    private function handleHealthCheck(array $payload, array $args): void {

    $target = $payload['target'] ?? 'production';
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        $this->json(['success' => false, 'error' => "Target '{$target}' not configured."], $args); return;
        return;
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
    $this->json($data, $args); return;

    }

    private function handleGetWebhooks(array $payload, array $args): void {

    $confFile = SPP_BASE_DIR . '/.sppdeploy.yml';
    $webhooks = [];
    if (file_exists($confFile)) {
        $conf = @yaml_parse_file($confFile);
        $webhooks = $conf['webhooks'] ?? [];
    }
    $this->json(['webhooks' => $webhooks], $args); return;

    }

    private function handleSaveWebhooks(array $payload, array $args): void {

    $webhooks = $payload['webhooks'] ?? [];
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
        $this->json(['success' => true, 'message' => 'Webhook configuration saved.'], $args); return;
    } else {
        $this->json(['success' => false, 'error' => 'Failed to save webhook configuration.'], $args); return;
    }

    }

    private function handleTestWebhook(array $payload, array $args): void {

    $url = $payload['url'] ?? '';
    if (!$url) $this->json(['success' => false, 'error' => 'Webhook URL is required.'], $args); return;
        return;
    
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
        $this->json(['success' => true, 'message' => 'Test webhook sent successfully!'], $args); return;
    } else {
        $this->json(['success' => false, 'error' => "Webhook failed with HTTP {$httpCode}."], $args); return;
    }

    }

    private function handleClusterStatus(array $payload, array $args): void {

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
    $this->json(['clusters' => $result], $args); return;

    }

    private function handleMaintenanceToggle(array $payload, array $args): void {

    $target = $payload['target'] ?? 'production';
    $enable = $payload['enable'] ?? true;
    $syncConfig = getSyncConfig();
    $env = $syncConfig['environments'][$target] ?? null;
    if (!$env || empty($env['url'])) {
        $this->json(['success' => false, 'error' => "Target '{$target}' not configured."], $args); return;
        return;
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
        $this->json(['success' => true, 'message' => $enable ? 'Maintenance mode enabled.' : 'Maintenance mode disabled.'], $args); return;
    } else {
        $this->json(['success' => false, 'error' => "Failed to toggle maintenance mode (HTTP {$httpCode})."], $args); return;
    }

    }

}
