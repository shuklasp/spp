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
                $modDir = SPP_MODULES_DIR . '/spp/' . $modName;
                $namespace = 'SPPMod\\' . $className;
                break;
            case 'optional':
                $modDir = SPP_MODULES_DIR . '/optional/' . $modName;
                $namespace = 'SPPMod\\' . $className;
                break;
            case 'contrib':
                $modDir = SPP_MODULES_DIR . '/contrib/' . $modName;
                $namespace = 'ContribMod\\' . $className;
                break;
            case 'app':
            default:
                // Ensure app name is properly formatted
                $appNameUc = str_replace('_', '', ucwords($appName, '_'));
                // Use the targeted app directory instead of the current active context
                $targetAppDir = SPP_APP_DIR . '/src/' . $appName;
                if (!is_dir($targetAppDir)) {
                    $targetAppDir = SPP_APP_DIR; // fallback if standard structure is different
                }
                $modDir = $targetAppDir . '/modules/' . $modName;
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
  provider: '{$namespace}\\{$className}ServiceProvider'
  dependencies:
    # List module dependencies here. Supports semantic versioning.
    # sppdb: ^2.0
    # sppauth: ^1.5
    spprouter: ^1.0
  includes:
    # Legacy includes (Prefer src/ for PSR-4 autoloading instead)
  assets:
    # Defines aliases for public assets. Usage: /sppasset/{$modName}/main/style.css
    main: /public
YAML;
        file_put_contents($modDir . '/module.yml', $yaml);

        // 2. db.yml (Modern Modularity 2.0 Schema Definitions)
        $dbYaml = <<<YAML
# db.yml
# Declarative database schema definitions for {$modName} module.
# Synced automatically via ModuleInstaller and `php spp.php module:update {$modName}`.
tables:
  # {$modName}_items:
  #   columns:
  #     id: "INTEGER PRIMARY KEY AUTOINCREMENT"
  #     name: "VARCHAR(255) NOT NULL"
  #     created_at: "DATETIME DEFAULT CURRENT_TIMESTAMP"
YAML;
        file_put_contents($modDir . '/db.yml', $dbYaml);

        // 3. modinit.php
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

        // 4. install.php
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

        // 5. uninstall.php
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

        // 6. events.yml
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

        // 7. Service Provider Stub
        $serviceProvider = <<<PHP
<?php

namespace {$namespace};

use SPP\\Core\\Interfaces\\ServiceProviderInterface;

/**
 * {$className}ServiceProvider
 * 
 * Register and bootstrap your module's services into the SPP Dependency Injection Container.
 */
class {$className}ServiceProvider implements ServiceProviderInterface
{
    /**
     * Register any application services into the container.
     */
    public function register(): void
    {
        // \$container = \\SPP\\App::getContainer();
        // \$container->singleton(MyService::class, fn() => new MyService());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Perform post-registration boot logic here
    }
}
PHP;
        file_put_contents($modDir . "/src/{$className}ServiceProvider.php", $serviceProvider);

        // 8. Parikshak Test Suite Stub
        $testStub = <<<PHP
<?php

namespace {$namespace}\\Tests;

use SPPMod\\Parikshak\\SPPTestCase;
use SPPMod\\Parikshak\\RefreshDatabase;

/**
 * Class {$className}Test
 * Automated test suite for module {$modName}.
 */
class {$className}Test extends SPPTestCase
{
    use RefreshDatabase;

    public function testModuleIsBootable(): void
    {
        \$module = \\SPP\\Module::getModule('{$modName}');
        \$this->assertNotNull(\$module, "Module '{$modName}' must resolve from the SPP Registry.");
        \$this->assertEquals('{$modName}', \$module->InternalName);
    }
}
PHP;
        file_put_contents($modDir . "/tests/{$className}Test.php", $testStub);

        echo "✅ Success: Module {$modName} scaffolded successfully at {$modDir}\n";
        echo "Namespace: {$namespace}\n";
        echo "Don't forget to enable it using: php spp.php module:enable {$modName}\n";
        echo "Run its tests using: php spp.php test:module {$modName}\n";
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
