<?php

namespace SPP\Core;

use Symfony\Component\Yaml\Yaml;

/**
 * class VersionManager
 * 
 * Manages the installed versions of modules using a flat YAML registry 
 * to ensure zero-dependency on the database module.
 */
class VersionManager
{
    private string $registryFile;

    public function __construct()
    {
        $this->registryFile = SPP_APP_DIR . SPP_DS . 'var' . SPP_DS . 'module_versions.yml';
    }

    /**
     * Gets the currently installed version of a module.
     */
    public function getInstalledVersion(string $modName): string
    {
        $versions = $this->loadRegistry();
        return $versions[$modName] ?? '0.0.0';
    }

    /**
     * Updates the installed version of a module in the registry.
     */
    public function updateVersion(string $modName, string $version): bool
    {
        $versions = $this->loadRegistry();
        $versions[$modName] = $version;
        return $this->saveRegistry($versions);
    }

    /**
     * Returns true if the module needs an upgrade (Manifest version > Registry version).
     */
    public function needsUpgrade(string $modName, string $manifestVersion): bool
    {
        $installed = $this->getInstalledVersion($modName);
        return version_compare($manifestVersion, $installed, '>');
    }

    /**
     * Synchronizes all active modules by running pending migrations.
     * Returns a log of actions taken.
     */
    public function syncAll(): array
    {
        $log = [];
        $registry = $this->loadRegistry();
        
        // Discover active modules (Simplified scan for migration discovery)
        $modules = $this->discoverModules();
        
        foreach ($modules as $name => $path) {
            $currentVersion = $registry[$name] ?? '0.0.0';
            $migrationDir = $path . SPP_DS . 'migrations';
            
            if (!is_dir($migrationDir)) continue;
            
            $files = glob($migrationDir . SPP_DS . 'Migration_*.php');
            sort($files); // Run in version order
            
            foreach ($files as $file) {
                $basename = basename($file, '.php');
                $version = str_replace(['Migration_', '_'], ['', '.'], $basename);
                
                if (version_compare($version, $currentVersion, '>')) {
                    try {
                        require_once $file;
                        $className = "\\SPPMod\\{$name}\\Migrations\\{$basename}";
                        if (class_exists($className)) {
                            $migration = new $className();
                            $migration->up();
                            $registry[$name] = $version;
                            $log[] = "✅ [{$name}] Upgraded to {$version}";
                        }
                    } catch (\Exception $e) {
                        $log[] = "❌ [{$name}] Failed at {$version}: " . $e->getMessage();
                    }
                }
            }
        }
        
        $this->saveRegistry($registry);
        return $log;
    }

    /**
     * Returns the full version registry.
     */
    public function getRegistry(): array
    {
        return $this->loadRegistry();
    }

    /**
     * Discovers all modules in system and user paths.
     */
    private function discoverModules(): array
    {
        $modules = [];
        $searchPaths = [
            SPP_MODULES_DIR,
            SPP_APP_DIR . SPP_DS . 'modules'
        ];

        foreach ($searchPaths as $base) {
            if (!is_dir($base)) continue;
            
            // Scan for directories that contain a manifest (yml or xml)
            $dirs = glob($base . SPP_DS . '*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $name = basename($dir);
                if (file_exists($dir . SPP_DS . 'manifest.yml') || file_exists($dir . SPP_DS . 'manifest.xml')) {
                    $modules[$name] = $dir;
                }
            }
        }
        return $modules;
    }

    private function loadRegistry(): array
    {
        if (!file_exists($this->registryFile)) {
            return [];
        }

        try {
            return Yaml::parseFile($this->registryFile) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function saveRegistry(array $versions): bool
    {
        $dir = dirname($this->registryFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        try {
            file_put_contents($this->registryFile, Yaml::dump($versions, 4, 4));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
