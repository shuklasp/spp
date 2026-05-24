<?php
namespace SPPMod\Lekhak\Core;

class ModuleRegistry
{
    protected static array $modules = [];
    protected static bool $initialized = false;
    protected static string $modulesDir = __DIR__ . '/../../../../modules';
    protected static string $cacheFile = __DIR__ . '/../../../../var/cache/module_registry.php';

    public static function init()
    {
        if (self::$initialized) return;
        self::$initialized = true;

        if (file_exists(self::$cacheFile)) {
            self::$modules = require self::$cacheFile;
        } else {
            self::scanModules();
            file_put_contents(self::$cacheFile, "<?php\nreturn " . var_export(self::$modules, true) . ";\n");
        }
    }

    public static function clearCache()
    {
        if (file_exists(self::$cacheFile)) {
            @unlink(self::$cacheFile);
        }
    }

    protected static function scanModules()
    {
        $directories = glob(self::$modulesDir . '/*', GLOB_ONLYDIR);
        foreach ($directories as $dir) {
            $machineName = basename($dir);
            $ymlPath = $dir . '/lekhak.module.yml';
            $infoYmlPath = $dir . '/' . $machineName . '.info.yml';
            $simpleYmlPath = $dir . '/module.yml';
            
            $manifest = [];
            if (file_exists($ymlPath)) {
                $manifest = self::parseYaml($ymlPath);
            } elseif (file_exists($infoYmlPath)) {
                $manifest = self::parseYaml($infoYmlPath);
            } elseif (file_exists($simpleYmlPath)) {
                $manifest = self::parseYaml($simpleYmlPath);
            }
            
            if (file_exists($dir . '/module.php')) {
                // Generate default manifest from directory name and parse docblock
                $content = file_get_contents($dir . '/module.php');
                $desc = 'Module ' . $machineName;
                $deps = [];
                $configure = null;
                if (preg_match('#/\*\*(.*?)\*/#s', $content, $matches)) {
                    $docblock = $matches[1];
                    $lines = explode("\n", $docblock);
                    $descLines = [];
                    foreach ($lines as $line) {
                        $line = trim(preg_replace('/^\*+/', '', trim($line)));
                        if (empty($line)) continue;
                        if (strpos($line, '@depends') === 0 || strpos($line, '@dependencies') === 0) {
                            $parts = preg_split('/[\s,]+/', preg_replace('/^@depend[a-z]*\s+/', '', $line));
                            $deps = array_merge($deps, array_filter($parts));
                        } elseif (strpos($line, '@configure') === 0) {
                            $configure = trim(preg_replace('/^@configure\s+/', '', $line));
                        } elseif (strpos($line, '@') !== 0) {
                            $descLines[] = $line;
                        }
                    }
                    if (!empty($descLines)) {
                        $desc = implode(' ', $descLines);
                    }
                }
                
                $manifest['name'] = $manifest['name'] ?? ucwords(str_replace('_', ' ', $machineName));
                $manifest['description'] = $manifest['description'] ?? $desc;
                $manifest['package'] = $manifest['package'] ?? 'Core';
                
                $existingDeps = $manifest['dependencies'] ?? [];
                if (is_string($existingDeps)) $existingDeps = preg_split('/[\s,]+/', $existingDeps);
                $manifest['dependencies'] = array_unique(array_merge($existingDeps, $deps));
                
                if (!isset($manifest['configure']) && isset($configure)) {
                    $manifest['configure'] = $configure;
                }
            } elseif (empty($manifest)) {
                continue; // Not a valid module directory
            }

            $deps = $manifest['dependencies'] ?? [];
            if (is_string($deps)) {
                $deps = preg_split('/[\s,]+/', $deps);
            }

            self::$modules[$machineName] = [
                'name' => $manifest['name'] ?? ucwords(str_replace('_', ' ', $machineName)),
                'description' => $manifest['description'] ?? 'No description provided.',
                'version' => $manifest['version'] ?? '1.0.0',
                'package' => $manifest['package'] ?? 'Core',
                'status' => self::isModuleEnabled($machineName),
                'installed' => self::isModuleInstalled($machineName),
                'configure' => $manifest['configure'] ?? null,
                'path' => $dir,
                'dependencies' => array_filter($deps)
            ];
        }
    }

    public static function getModules(): array
    {
        self::init();
        return self::$modules;
    }

    public static function enableModule(string $machineName)
    {
        // 1. Install if not installed
        if (!self::isModuleInstalled($machineName)) {
            self::installModule($machineName);
        }

        $enabled = self::getEnabledModulesList();
        if (!in_array($machineName, $enabled)) {
            $enabled[] = $machineName;
            self::saveEnabledModulesList($enabled);
        }

        // 2. Run any pending updates when a module is enabled
        self::runUpdates();

        if (isset(self::$modules[$machineName])) {
            self::$modules[$machineName]['status'] = true;
        }
        self::clearCache();
    }

    protected static function installModule(string $machineName)
    {
        $installed = self::getInstalledModulesList();
        if (!isset($installed[$machineName])) {
            $instance = self::getModuleInstance($machineName);
            if ($instance && method_exists($instance, 'hook_install')) {
                $instance->hook_install();
            }
            // Mark as installed with default schema version 8000
            $installed[$machineName] = 8000;
            self::saveInstalledModulesList($installed);
            
            if (isset(self::$modules[$machineName])) {
                self::$modules[$machineName]['installed'] = true;
            }
        }
    }

    public static function disableModule(string $machineName)
    {
        $enabled = self::getEnabledModulesList();
        $index = array_search($machineName, $enabled);
        if ($index !== false) {
            unset($enabled[$index]);
            self::saveEnabledModulesList(array_values($enabled));
            
            if (isset(self::$modules[$machineName])) {
                self::$modules[$machineName]['status'] = false;
            }
            self::clearCache();
        }
    }

    public static function uninstallModule(string $machineName)
    {
        self::disableModule($machineName);
        
        $installed = self::getInstalledModulesList();
        if (isset($installed[$machineName])) {
            $instance = self::getModuleInstance($machineName);
            if ($instance && method_exists($instance, 'hook_uninstall')) {
                $instance->hook_uninstall();
            }
            unset($installed[$machineName]);
            self::saveInstalledModulesList($installed);
            
            if (isset(self::$modules[$machineName])) {
                self::$modules[$machineName]['installed'] = false;
                self::$modules[$machineName]['status'] = false;
            }
            self::clearCache();
        }
    }

    public static function runUpdates()
    {
        $installed = self::getInstalledModulesList();
        $updatesRan = false;
        
        foreach (self::$modules as $machineName => $info) {
            if (!isset($installed[$machineName])) continue; // Skip uninstalled modules
            
            $currentVersion = $installed[$machineName];
            $instance = self::getModuleInstance($machineName);
            if (!$instance) continue;

            $methods = get_class_methods($instance);
            $updateMethods = [];
            foreach ($methods as $method) {
                if (preg_match('/^hook_update_(\d+)$/', $method, $matches)) {
                    $updateVersion = (int)$matches[1];
                    if ($updateVersion > $currentVersion) {
                        $updateMethods[$updateVersion] = $method;
                    }
                }
            }

            if (!empty($updateMethods)) {
                ksort($updateMethods); // Run in numerical order
                foreach ($updateMethods as $v => $m) {
                    $instance->$m();
                    $currentVersion = $v;
                }
                $installed[$machineName] = $currentVersion;
                $updatesRan = true;
            }
        }

        if ($updatesRan) {
            self::saveInstalledModulesList($installed);
        }
    }

    protected static function getModuleInstance(string $machineName)
    {
        if (!isset(self::$modules[$machineName])) return null;
        $dir = self::$modules[$machineName]['path'];
        $phpFile = $dir . '/module.php';
        $drupalModuleFile = $dir . '/' . $machineName . '.module';
        
        if (file_exists($phpFile)) {
            require_once $phpFile;
            
            // Guess namespace based on standard: Lekhak\Modules\ModuleName\LekhakModuleModuleName
            $humanName = str_replace(' ', '', ucwords(str_replace('_', ' ', $machineName)));
            
            $namespaces = [
                "Lekhak\\Modules\\{$humanName}\\LekhakModule{$humanName}",
                "App\\Lekhak\\Modules\\{$humanName}\\LekhakModule{$humanName}"
            ];
            
            // Some modules might not use the namespace, try looking for just LekhakModuleModuleName
            if (class_exists("LekhakModule{$humanName}")) {
                $cls = "LekhakModule{$humanName}";
                return new $cls();
            }

            foreach ($namespaces as $ns) {
                if (class_exists($ns)) {
                    return new $ns();
                }
            }
            
            // Read file to find actual namespace and class
            $content = file_get_contents($phpFile);
            if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch) && preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $clsMatch)) {
                $fullCls = trim($nsMatch[1]) . '\\' . trim($clsMatch[1]);
                if (class_exists($fullCls)) {
                    return new $fullCls();
                }
            }
        } elseif (file_exists($drupalModuleFile)) {
            require_once $drupalModuleFile;
            // Drupal modules are procedural and don't return an instance object.
            return null;
        }
        
        return null;
    }

    public static function isModuleEnabled(string $machineName): bool
    {
        return in_array($machineName, self::getEnabledModulesList());
    }

    public static function isModuleInstalled(string $machineName): bool
    {
        $installed = self::getInstalledModulesList();
        return isset($installed[$machineName]);
    }

    protected static function getInstalledModulesList(): array
    {
        $file = self::$modulesDir . '/../etc/installed_modules.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        // Core is always installed
        return ['lekhak' => 8000]; 
    }

    protected static function saveInstalledModulesList(array $list)
    {
        $file = self::$modulesDir . '/../etc/installed_modules.json';
        file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT));
    }

    protected static function getEnabledModulesList(): array
    {
        $file = self::$modulesDir . '/../etc/enabled_modules.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return ['lekhak']; // Core is always enabled
    }

    protected static function saveEnabledModulesList(array $list)
    {
        $file = self::$modulesDir . '/../etc/enabled_modules.json';
        file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT));
    }

    /**
     * Very basic YAML parser for standard metadata blocks.
     * In a full system, we'd use Symfony Yaml component.
     */
    protected static function parseYaml(string $path): array
    {
        $data = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $data[trim($parts[0])] = trim($parts[1], " '\"");
            }
        }
        return $data;
    }
}
