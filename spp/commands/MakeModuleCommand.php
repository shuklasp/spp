<?php

namespace SPP\CLI\Commands;

/**
 * Class MakeModuleCommand
 * Scaffolds a new SPP module with modern architecture (module.yml, modinit.php, install.php, etc.).
 */
class MakeModuleCommand extends BaseMakeCommand
{
    protected string $name = 'make:module';
    protected string $description = 'Create a new SPP module (System or App level)';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $name = $this->getArgument($args, 0) ?? null;
        if (!$name) {
            echo "Usage: php spp.php make:module <name> [--scope=spp|optional|contrib|app] [--app=AppName]\n";
            return;
        }

        $scope = 'app';
        $appName = basename(SPP_APP_DIR); // Default to current app
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--scope=')) {
                $scope = substr($arg, 8);
            }
            if (str_starts_with($arg, '--app=')) {
                $appName = substr($arg, 6);
            }
        }

        $modName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $name));
        $className = str_replace('_', '', ucwords($modName, '_'));
        
        $modDir = '';
        $namespace = '';
        
        switch ($scope) {
            case 'spp':
                $modDir = SPP_BASE_DIR . '/modules/spp/' . $modName;
                $namespace = 'SPPMod\\' . $className;
                break;
            case 'optional':
                $modDir = SPP_BASE_DIR . '/modules/optional/' . $modName;
                $namespace = 'SPPMod\\' . $className;
                break;
            case 'contrib':
                $modDir = SPP_BASE_DIR . '/modules/contrib/' . $modName;
                $namespace = 'ContribMod\\' . $className;
                break;
            case 'app':
            default:
                // Ensure app name is properly formatted
                $appNameUc = str_replace('_', '', ucwords($appName, '_'));
                // Use the targeted app directory instead of the current active context
                $targetAppDir = SPP_BASE_DIR . '/src/' . $appName;
                if (!is_dir($targetAppDir)) {
                    $targetAppDir = SPP_APP_DIR; // fallback if standard structure is different
                }
                $modDir = $targetAppDir . '/src/modules/' . $modName;
                $namespace = 'AppMod\\' . $appNameUc . '\\' . $className;
                break;
        }

        if (is_dir($modDir)) {
            echo "❌ Error: Module '{$modName}' already exists at {$modDir}.\n";
            return;
        }

        mkdir($modDir, 0777, true);
        mkdir($modDir . '/src', 0777, true);
        mkdir($modDir . '/public', 0777, true);

        // 1. module.yml
        $yaml = <<<YAML
module:
  name: {$modName}
  version: '1.0.0'
  type: module
  compulsory: false
  publicname: '{$className} Module'
  publicdesc: 'A newly scaffolded module.'
  namespace: '{$namespace}'
  dependencies:
    # List module dependencies here. Supports semantic versioning.
    # sppdb: ^2.0
    # sppauth: ^1.5
    spprouter: ^1.0
  includes:
    # Legacy includes (Try to use src/ for PSR-4 autoloading instead)
  assets:
    # Defines aliases for public assets. Usage: /sppasset/{$modName}/main/style.css
    main: /public
YAML;
        file_put_contents($modDir . '/module.yml', $yaml);

        // 2. modinit.php
        $modinit = <<<PHP
<?php
/**
 * modinit.php
 * 
 * Executed every time the SPP framework boots and this module is active.
 * Use this file to:
 * - Register global state
 * - Register template components or directives
 * - Bootstrap module-specific configurations
 * 
 * Note: For event listeners, prefer using events.yml for zero-boot performance.
 */

// Example: \\SPP\\Registry::register('{$modName}.loaded', true);
PHP;
        file_put_contents($modDir . '/modinit.php', $modinit);

        // 3. install.php
        $install = <<<PHP
<?php
/**
 * install.php
 * 
 * Executed ONLY once when the module is installed via `php spp.php module:install {$modName}`.
 * Use this file to:
 * - Seed initial database rows
 * - Create necessary storage directories
 * - Set up third-party webhooks
 */

\$db = \SPP\Core\ModuleInstaller::getDb();
// Example: \$db->execute_query("INSERT INTO settings (key, val) VALUES (?, ?)", ['{$modName}_active', '1']);
PHP;
        file_put_contents($modDir . '/install.php', $install);

        // 4. uninstall.php
        $uninstall = <<<PHP
<?php
/**
 * uninstall.php
 * 
 * Executed ONLY once when the module is uninstalled via `php spp.php module:uninstall {$modName}`.
 * Use this file to:
 * - Clean up physical files or directories
 * - Remove third-party webhooks
 * - (Optional) Remove database tables, though retaining them prevents data loss.
 */

\$db = \SPP\Core\ModuleInstaller::getDb();
// Example: \$db->execute_query("DELETE FROM settings WHERE key = ?", ['{$modName}_active']);
PHP;
        file_put_contents($modDir . '/uninstall.php', $uninstall);

        // 5. events.yml
        $events = <<<YAML
# events.yml
# 
# Defines event listeners for this module.
# SPP uses Zero-Boot Event Discovery, meaning these are cached globally, 
# and the module is dynamically loaded only when the event actually fires.
# 
# Format:
# event.name:
#   - {$namespace}\\Listeners\\ExampleListener::handle

# Example:
# user.registered:
#   - {$namespace}\\Listeners\\UserEventSubscriber::onUserRegistered
YAML;
        file_put_contents($modDir . '/events.yml', $events);

        // 6. Service Provider Stub
        $serviceProvider = <<<PHP
<?php

namespace {$namespace};

/**
 * {$className}ServiceProvider
 * 
 * Register your module's classes into the SPP Dependency Injection Container.
 */
class {$className}ServiceProvider
{
    public function register(\\SPP\\Core\\Container \$container): void
    {
        // \$container->singleton(MyService::class, fn() => new MyService());
    }
}
PHP;
        file_put_contents($modDir . "/src/{$className}ServiceProvider.php", $serviceProvider);

        echo "✅ Success: Module {$modName} scaffolded successfully at {$modDir}\n";
        echo "Namespace: {$namespace}\n";
        echo "Don't forget to enable it using: php spp.php module:enable {$modName}\n";
    }

    public function renderAdminUI(): string
    {
        $name = htmlspecialchars($this->getName());
        $html = '<div class="command-ui-container">';
        $html .= '  <h3>Create Module</h3>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Module Name (e.g. blog, forum):</label>';
        $html .= '    <input type="text" id="arg_name" class="spp-input">';
        $html .= '  </div>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Scope / Location:</label>';
        $html .= '    <select id="arg_scope" class="spp-input">';
        $html .= '      <option value="app">App-Level (Local)</option>';
        $html .= '      <option value="optional">Optional (Core Modules)</option>';
        $html .= '      <option value="contrib">Contrib (Community Plugins)</option>';
        $html .= '      <option value="spp">SPP Core (Framework)</option>';
        $html .= '    </select>';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn spp-btn-primary" onclick="executeCustomModuleCreate()">Create Module</button>';
        $html .= '  <script>
                        function executeCustomModuleCreate() {
                            let name = document.getElementById("arg_name").value;
                            let scope = document.getElementById("arg_scope").value;
                            let args = name + " --scope=" + scope;
                            executeCommand("' . $name . '", args);
                        }
                    </script>';
        $html .= '</div>';
        return $html;
    }
}
