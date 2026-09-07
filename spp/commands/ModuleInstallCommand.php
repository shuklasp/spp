<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use SPP\Core\ModuleInstaller;

/**
 * Class ModuleInstallCommand
 *
 * Installs, activates, and configures framework-level modules or app-level modules.
 * Supports:
 *   - Core framework modules (spp/modules/spp, spp/modules/contrib)
 *   - App-level modules via --app=AppName flag (e.g. src/SPPDocs/modules)
 *   - Batch installation via --all flag
 *
 * @package SPP\CLI\Commands
 * @author Satya Prakash Shukla
 */
class ModuleInstallCommand extends Command
{
    protected string $name = 'module:install';
    protected string $description = 'Install or activate a specific module or all active modules';

    /**
     * Strict CLI SAPI guard to prevent execution from web contexts.
     */
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $all = $this->hasFlag($args, 'all');
        $app = $this->getOption($args, 'app');
        $project = $this->getOption($args, 'project', 'spp');

        $moduleName = $this->getArgument($args, 0);

        if (!$all && !$moduleName) {
            $this->line("Usage: php spp.php module:install <modulename> [--app=AppName] [--project=ProjectId] [--all]");
            return;
        }

        // App-Level Module Installation
        if ($app) {
            $this->installAppModule($app, $moduleName, $project, $all);
            return;
        }

        // Core Framework Module Installation
        if ($all) {
            $this->line("📦 Installing all active core framework modules...");
            $results = ModuleInstaller::installAllActive();
            foreach ($results as $name => $res) {
                $icon = $res['success'] ? '✅' : '❌';
                $this->line("  {$icon} " . str_pad($name, 20) . " " . $res['message']);
            }
        } else {
            $this->line("📦 Activating and installing core module '{$moduleName}'...");
            try {
                // Activate the module first so it gets discovered by the framework
                ModuleInstaller::setModuleStatus($moduleName, 'active');

                // Clear module cache and force reload to pick up the newly activated module
                if (class_exists('\SPP\Module')) {
                    \SPP\Module::loadAllModules(true);
                }

                $res = ModuleInstaller::install($moduleName);
                if ($res) {
                    $this->info("✅ Module '{$moduleName}' successfully activated and installed.");
                } else {
                    $this->warn("⚠️  Module installation completed with non-fatal status.");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle app-level module activation/installation.
     */
    private function installAppModule(string $app, ?string $moduleName, string $project, bool $all): void
    {
        $this->line("📦 App-level module management for Application [{$app}]...");

        $appModuleClass = "\\App\\{$app}\\Services\\ModuleManager";
        if (class_exists($appModuleClass)) {
            if ($all) {
                $available = $appModuleClass::listAvailableModules($project);
                foreach ($available as $id => $meta) {
                    $res = $appModuleClass::enableModule($project, $id);
                    $icon = $res ? '✅' : '❌';
                    $this->line("  {$icon} Enabled {$id} (" . ($meta['name'] ?? $id) . ")");
                }
                $this->info("✅ All available modules enabled for app {$app}.");
            } else {
                $res = $appModuleClass::enableModule($project, $moduleName);
                if ($res) {
                    $this->info("✅ Module '{$moduleName}' successfully enabled for {$app} (Project: {$project}).");
                } else {
                    $this->error("❌ Failed to enable module '{$moduleName}' for {$app}.");
                }
            }
            return;
        }

        // Generic app module folder check
        $appModDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 2)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $moduleName;
        if (is_dir($appModDir)) {
            $this->info("✅ Located app module directory at: {$appModDir}");
            $this->info("Module is ready for inclusion in {$app}.");
        } else {
            $this->error("❌ Module directory not found: {$appModDir}");
        }
    }
}
