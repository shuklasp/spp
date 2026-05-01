<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;

/**
 * Class ForgeCommand
 * The next-gen automation engine for SPP.
 * Handles unified scaffolding and developer productivity tools like LiveSync.
 */
class ForgeCommand extends Command
{
    protected string $name = 'forge';
    protected string $description = 'Unified automation and LiveSync engine';

    public function execute(array $args): void
    {
        $sub = $args[2] ?? 'help';

        switch ($sub) {
            case 'module':
                $this->forgeModule($args);
                break;
            case 'component':
                $this->forgeComponent($args);
                break;
            case 'livesync':
                $this->startLiveSync($args);
                break;
            case 'compile':
                $this->compileModules($args);
                break;
            case 'migrate':
                $this->runMigrations($args);
                break;
            case 'migration':
                $this->forgeMigration($args);
                break;
            default:
                $this->showHelp();
                break;
        }
    }

    private function forgeMigration(array $args): void
    {
        $mod = $args[3] ?? null;
        $ver = $args[4] ?? null;

        if (!$mod || !$ver) {
            echo "Error: Module name and version required. Usage: php spp forge migration <module> <version>\n";
            return;
        }

        $modObj = \SPP\Module::getModule($mod);
        $path = $modObj->ModPath . SPP_DS . 'migrations';
        if (!is_dir($path)) mkdir($path, 0755, true);

        $className = "Migration_" . str_replace('.', '_', $ver);
        $file = $path . SPP_DS . $className . ".php";

        if (file_exists($file)) {
            echo "Error: Migration for version {$ver} already exists for module [{$mod}].\n";
            return;
        }

        $tpl = "<?php\n\nnamespace SPPMod\\{$mod}\\Migrations;\n\nuse SPP\\Core\\Migration;\n\nclass {$className} extends Migration\n{\n    public function getVersion(): string\n    {\n        return '{$ver}';\n    }\n\n    public function up(): void\n    {\n        // \$this->executeSql(\"CREATE TABLE ...\");\n    }\n\n    public function down(): void\n    {\n        // \$this->executeSql(\"DROP TABLE ...\");\n    }\n}\n";
        
        file_put_contents($file, $tpl);
        echo "✅ Success: Migration boilerplate created at {$file}\n";
    }

    private function compileModules(array $args): void
    {
        $app = $args[3] ?? \SPP\Scheduler::getContext() ?: 'default';
        echo "📦 Compiling module manifests for context [{$app}]...\n";

        $compiler = new \SPP\Core\ModuleCompiler($app);
        if ($compiler->compile()) {
            echo "✅ Success: Module cache compiled at " . \SPP\Core\ModuleCompiler::getCachePath($app) . "\n";
        } else {
            echo "❌ Error: Compilation failed.\n";
        }
    }
    private function runMigrations(array $args): void
    {
        echo "🛠️  Running module migrations...\n";
        
        $vm = new \SPP\Core\VersionManager();
        $app = \SPP\Scheduler::getContext() ?: 'default';
        
        // Use the cache to find all active modules
        $cacheFile = \SPP\Core\ModuleCompiler::getCachePath($app);
        if (!file_exists($cacheFile)) {
            echo "⚠️  No module cache found. Compiling first...\n";
            $compiler = new \SPP\Core\ModuleCompiler($app);
            $compiler->compile();
        }
        
        $modules = require $cacheFile;
        $count = 0;

        foreach ($modules as $name => $data) {
            $manifestVersion = $data['version'] ?? '0.0.0';
            if ($vm->needsUpgrade($name, $manifestVersion)) {
                $installed = $vm->getInstalledVersion($name);
                echo "⬆️  Upgrading [{$name}] ({$installed} -> {$manifestVersion})...\n";
                
                $migrated = $this->executeModuleMigrations($name, $data['path'], $installed, $manifestVersion);
                if ($migrated) {
                    $vm->updateVersion($name, $manifestVersion);
                    echo "✅ [{$name}] migrated to {$manifestVersion}.\n";
                    $count++;
                }
            }
        }

        echo "🏁 Migration finished. [{$count}] modules updated.\n";
    }

    private function executeModuleMigrations(string $name, string $path, string $from, string $to): bool
    {
        $dir = $path . SPP_DS . 'migrations';
        if (!is_dir($dir)) return true; // No migrations folder is a silent success

        $files = scandir($dir);
        sort($files);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            // Expected format: Migration_1_1_0.php
            if (preg_match('/Migration_([0-9_]+)\.php/', $file, $matches)) {
                $version = str_replace('_', '.', $matches[1]);
                
                if (version_compare($version, $from, '>') && version_compare($version, $to, '<=')) {
                    require_once $dir . SPP_DS . $file;
                    $className = "SPPMod\\{$name}\\Migrations\\Migration_" . $matches[1];
                    
                    if (class_exists($className)) {
                        $migration = new $className();
                        echo "  🏃 Running {$file}...\n";
                        $migration->up();
                    }
                }
            }
        }
        return true;
    }

    private function forgeModule(array $args): void
    {
        $name = $args[3] ?? null;
        if (!$name) {
            echo "Error: Module name required. Usage: php spp forge module <name>\n";
            return;
        }

        $base = SPP_BASE_DIR . '/modules/spp/' . strtolower($name);
        if (is_dir($base)) {
            echo "Error: Module {$name} already exists.\n";
            return;
        }

        mkdir($base, 0777, true);
        mkdir($base . '/js', 0777, true);
        mkdir($base . '/css', 0777, true);

        $className = ucfirst($name);
        $init = "<?php\n\nnamespace SPPMod\\{$className};\n\nclass {$className} extends \\SPP\\Module {\n    public function init() {\n        // Module initialization\n    }\n}\n";
        file_put_contents($base . "/class.{$name}.php", $init);
        file_put_contents($base . "/modinit.php", "<?php\n\n\\SPPMod\\{$className}\\{$className}::boot();\n");

        echo "Success: Module {$name} forged at {$base}\n";
    }

    private function forgeComponent(array $args): void
    {
        // Reuse MakeUXComponentCommand logic but modernized
        $name = $args[3] ?? null;
        if (!$name) {
            echo "Error: Component name required. Usage: php spp forge component <name>\n";
            return;
        }
        
        // Call the existing make:ux-component command for now to ensure consistency
        $cmd = new MakeUXComponentCommand();
        $cmd->execute($args);
    }

    private function startLiveSync(array $args): void
    {
        echo "🚀 Starting SPP LiveSync Watcher...\n";
        echo "Watching: spp/modules, spp/apps, src\n";
        
        $lastMtime = $this->getLatestMtime();
        
        while (true) {
            clearstatcache();
            $currentMtime = $this->getLatestMtime();
            
            if ($currentMtime > $lastMtime) {
                echo "⚡ Change detected! Notifying clients...\n";
                $this->notifyLiveSync($currentMtime);
                $lastMtime = $currentMtime;
            }
            
            usleep(500000); // 500ms poll
        }
    }

    private function getLatestMtime(): int
    {
        $dirs = [
            SPP_BASE_DIR . '/modules',
            SPP_BASE_DIR . '/apps',
            dirname(SPP_BASE_DIR) . '/src'
        ];
        
        $max = 0;
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $max = max($max, $file->getMTime());
                }
            }
        }
        return $max;
    }

    private function notifyLiveSync(int $time): void
    {
        // For a true WebSocket, we'd need a real server. 
        // For now, we'll use a simple file-based sentinel that the JS can poll.
        $sentinel = SPP_BASE_DIR . '/.livesync';
        file_put_contents($sentinel, $time);
    }

    private function showHelp(): void
    {
        echo "SPP Forge Engine v1.0\n";
        echo "Usage:\n";
        echo "  php spp forge module <name>    - Create a new module\n";
        echo "  php spp forge component <name> - Create a new UX component\n";
        echo "  php spp forge livesync         - Start development watcher\n";
        echo "  php spp forge compile <app>    - Compile module cache for an app context\n";
        echo "  php spp forge migrate          - Run pending module migrations\n";
        echo "  php spp forge migration <mod> <ver> - Create a migration boilerplate\n";
    }
}
