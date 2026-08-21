<?php
namespace SPP\CLI\Commands;

use SPP\CLI\Command;

class DevModulesCommand extends Command
{
    protected string $name = 'dev:modules';
    protected string $description = 'Manage Dev Modules operations. Usage: admin:modules <action> [--payload=...] [--json]';

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
    $modules = \SPP\Module::listAvailableModules($appname);
    $this->json(['modules' => $modules], $args); return;

    }

    private function handleScan(array $payload, array $args): void {

    $modname = $payload['modname'] ?? '';
    if (!$modname) $this->json(['success' => false, 'error' => "Module name required."], $args); return;
        return;
    
    $manifest = \SPP\Module::findManifestPath($modname);
    if (!$manifest) $this->json(['success' => false, 'error' => "Module manifest not found."], $args); return;
        return;
    
    $mod = new \SPP\Module($manifest);
    $deltas = $mod->getInstallationDeltas();
    
    $this->notify("Scan complete.", $args);
        $this->json(['deltas' => $deltas]); return;

    }

    private function handleSetup(array $payload, array $args): void {

    $modname = $payload['modname'] ?? '';
    if (!$modname) $this->json(['success' => false, 'error' => "Module name required."], $args); return;
        return;
    
    try {
        if (\SPP\Core\ModuleInstaller::install($modname)) {
            $this->json(['success' => true, 'message' => "Module '$modname' setup successful.", "success"], $args); return;
            $la->dispatch('refresh');
        } else {
            $this->json(['success' => false, 'error' => "Setup failed."], $args); return;
        }
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Setup Error: " . $e->getMessage()], $args); return;
    }

    }

    private function handleInstallAllActive(array $payload, array $args): void {

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
        
        $this->json(['success' => true, 'message' => $msg, $failCount === 0 ? "success" : "warning"], $args); return;
        $la->dispatch('refresh');
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Bulk Install Error: " . $e->getMessage()], $args); return;
    }

    }

    private function handleUninstall(array $payload, array $args): void {

    $modname = $payload['modname'] ?? '';
    if (!$modname) $this->json(['success' => false, 'error' => "Module name required."], $args); return;
        return;
    
    try {
        if (\SPP\Core\ModuleInstaller::uninstall($modname)) {
            $this->json(['success' => true, 'message' => "Module '$modname' uninstalled successful.", "success"], $args); return;
            $la->dispatch('refresh');
        } else {
            $this->json(['success' => false, 'error' => "Uninstall failed."], $args); return;
        }
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => "Uninstall Error: " . $e->getMessage()], $args); return;
    }

    }

    private function handleGetconfig(array $payload, array $args): void {

    $modname = $payload['modname'] ?? '';
    $appname = $payload['appname'] ?? 'default';
    if (!$modname) $this->json(['success' => false, 'error' => "Module name required."], $args); return;
        return;
    
    $config = \SPP\Module::getAppConfig($modname, $appname);
    $this->json(['config' => $config], $args); return;

    }

    private function handleSaveconfig(array $payload, array $args): void {

    $modname = $payload['modname'] ?? '';
    $appname = $payload['appname'] ?? 'default';
    $config = $payload['config'] ?? [];
    
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
        $this->json(['success' => true, 'message' => "Module '$modname' config saved.", "success"], $args); return;
    } else {
        file_put_contents($logFile, "[Modules] SaveSettings: FAILED to write to $path\n", FILE_APPEND);
        $this->json(['success' => false, 'error' => "Save failed: Could not write to $path"], $args); return;
    }

    }

    private function handleSaveconfigraw(array $payload, array $args): void {

    $modname = $payload['modname'] ?? '';
    $appname = $payload['appname'] ?? 'default';
    $content = $payload['content'] ?? '';
    $format = $payload['format'] ?? 'yml';
    
    // Low-level write logic using canonical path
    $path = \SPP\Module::getExpectedConfigPath($modname, $appname);
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    if (file_put_contents($path, $content)) {
        $this->json(['success' => true, 'message' => "Raw config for '$modname' saved.", "success"], $args); return;
    } else {
        $this->json(['success' => false, 'error' => "Raw save failed."], $args); return;
    }

    }

    private function handleOpensettings(array $payload, array $args): void {

    file_put_contents(SPP_BASE_DIR . "/api_debug.log", "[Modules] OpenSettings called with: " . json_encode($payload) . "\n", FILE_APPEND);
    $modname = $payload['modname'] ?? '';
    $appname = $payload['appname'] ?? 'default';
    $publicName = $payload['public_name'] ?? $modname;
    
    if (!$modname) $this->json(['success' => false, 'error' => "Module name required."], $args); return;
        return;
    
    $manifestPath = \SPP\Module::findManifestPath($modname);
    if (!$manifestPath) $this->json(['success' => false, 'error' => "Module manifest not found."], $args); return;
        return;
    
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
    ob_start(); require SPP_APP_DIR . '/spp/admin/partials/generated/devmodules_1.php'; $html = ob_get_clean();
    
    $la->modal("Settings: $publicName", $html, [
        ['label' => 'Cancel', 'fn' => 'close'],
        ['label' => 'Save Changes', 'type' => 'primary', 'fn' => 'save']
    ]);
    
    $this->json([
        'modname' => $modname,
        'appname' => $appname
    ], $args); return;

    }

    private function handleToggle(array $payload, array $args): void {

    $modname = $payload['modname'] ?? ($payload['name'] ?? '');
    $status = $payload['status'] ?? ($payload['active'] ? 'active' : 'inactive');
    $appname = $payload['appname'] ?? 'default';
    
    if (!$modname) $this->json(['success' => false, 'error' => "Module name required."], $args); return;
        return;
    
    try {
        \SPP\Core\ModuleInstaller::setModuleStatus($modname, $status);
        $this->json(['success' => true, 'message' => "Module '$modname' status updated to $status.", "success"], $args); return;
        // Trigger a list refresh if the component supports it
        $la->dispatch('refresh');
    } catch (\Exception $e) {
        $this->json(['success' => false, 'error' => $e->getMessage()], $args); return;
    }

    }

}
