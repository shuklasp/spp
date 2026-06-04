<?php

namespace SPP\Core;

use SPP\Module;
use Symfony\Component\Yaml\Yaml;

/**
 * class ModuleCompiler
 *
 * Compiles the distributed module manifests and configurations into a
 * single optimized PHP array for zero-I/O bootstrapping.
 */
class ModuleCompiler
{
    private string $cacheFile;
    private string $appContext;

    public function __construct(string $appContext = 'default')
    {
        $this->appContext = $appContext;
        $this->cacheFile = SPP_APP_DIR . SPP_DS . 'var' . SPP_DS . 'cache' . SPP_DS . 'modules_' . $appContext . '.php';
    }

    /**
     * Performs the full compilation of active modules.
     */
    public function compile(): bool
    {
        try {
            $registry = $this->compileToArray();
            return $this->writeCache($registry);
        } catch (\Exception $e) {
            echo "❌ Compilation Error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Compiles modules to an array, enforcing topological sorting.
     */
    public function compileToArray(): array
    {
        $registry = [];
        $discovered = $this->discoverActiveModules();

        // Sort modules by dependency graph (Topological Sort)
        $sortedNames = $this->topologicalSort($discovered);

        foreach ($sortedNames as $modName) {
            $modData = $discovered[$modName];
            $manifestPath = $modData['manifest'];
            try {
                $module = new Module($manifestPath);
                $module->ModuleType = $modData['type'];

                // Collect basic metadata
                $registry[$modName] = [
                    'name' => $module->InternalName,
                    'path' => $module->ModPath,
                    'type' => $module->ModuleType,
                    'version' => $module->Version,
                    'dependencies' => $module->Dependencies,
                    'includes' => $module->IncludeFiles,
                    'services' => $this->extractServices($module),
                    'config' => $this->extractConfig($module)
                ];
            } catch (\Exception $e) {
                // Skip broken modules during compilation
                continue;
            }
        }

        return $registry;
    }

    /**
     * Performs a topological sort on the discovered modules to resolve dependency order.
     */
    private function topologicalSort(array $modules): array
    {
        $sorted = [];
        $visited = [];
        $temp = [];

        $visit = function ($name) use (&$visit, &$sorted, &$visited, &$temp, $modules) {
            if (isset($temp[$name])) {
                throw new \Exception("Circular dependency detected involving module '{$name}'");
            }
            if (!isset($visited[$name])) {
                $temp[$name] = true;

                // Fetch dependencies from manifest (need to parse minimal manifest here)
                $manifestPath = $modules[$name]['manifest'] ?? null;
                if ($manifestPath) {
                    $ext = strtolower(pathinfo($manifestPath, PATHINFO_EXTENSION));
                    $deps = [];
                    if ($ext === 'yml' || $ext === 'yaml') {
                        $parsed = Yaml::parseFile($manifestPath);
                        $deps = $parsed['module']['deps'] ?? ($parsed['module']['dependencies'] ?? []);
                    }

                    foreach ($deps as $dep) {
                        if (isset($modules[$dep])) {
                            $visit($dep);
                        } else {
                            throw new \SPP\Exceptions\MissingDependencyException("Module '{$name}' requires missing or inactive dependency '{$dep}'");
                        }
                    }
                }

                unset($temp[$name]);
                $visited[$name] = true;
                $sorted[] = $name;
            }
        };

        foreach (array_keys($modules) as $name) {
            $visit($name);
        }

        return $sorted;
    }

    /**
     * Discovers which modules should be active based on manifest hierarchy.
     */
    private function discoverActiveModules(): array
    {
        $active = [];
        $manifests = [
            ['file' => SPP_ETC_DIR . SPP_DS . 'modules.yml', 'type' => 'system'],
            ['file' => SPP_ETC_DIR . SPP_DS . 'apps' . SPP_DS . $this->appContext . SPP_DS . 'modules.yml', 'type' => 'system'],
            ['file' => APP_ETC_DIR . SPP_DS . $this->appContext . SPP_DS . 'modsconf' . SPP_DS . 'modules.yml', 'type' => 'user']
        ];

        foreach ($manifests as $m) {
            if (!file_exists($m['file'])) {
                continue;
            }

            $data = Yaml::parseFile($m['file']);
            $mods = $data['modules'] ?? [];

            foreach ($mods as $mod) {
                $name = $mod['name'] ?? $mod['modname'] ?? null;
                $status = $mod['status'] ?? 'active';

                if ($name && ($status === 'active' || Module::isCompulsory($name))) {
                    $manifestPath = Module::findManifestPath($name, $m['type'], $this->appContext);
                    if ($manifestPath) {
                        $active[$name] = [
                            'manifest' => $manifestPath,
                            'type' => $m['type']
                        ];
                    }
                }
            }
        }

        // Also discover loosely coupled app-specific modules from src directories
        foreach (Module::getAppModuleDirs($this->appContext) as $appModuleDir) {
            if (!is_dir($appModuleDir)) {
                continue;
            }
            $dirs = scandir($appModuleDir);
            foreach ($dirs as $d) {
                if ($d === '.' || $d === '..') {
                    continue;
                }
                $dirPath = $appModuleDir . SPP_DS . $d;
                if (!is_dir($dirPath)) {
                    continue;
                }

                $manifestPath = null;
                foreach (['module.yml', 'module.yaml'] as $m) {
                    if (file_exists($dirPath . SPP_DS . $m)) {
                        $manifestPath = $dirPath . SPP_DS . $m;
                        break;
                    }
                }
                if ($manifestPath) {
                    // Quick parse to get name
                    try {
                        $parsed = Yaml::parseFile($manifestPath);
                        $name = $parsed['module']['name'] ?? $parsed['module']['id'] ?? $d;
                        $active[$name] = [
                            'manifest' => $manifestPath,
                            'type' => 'user'
                        ];
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        return $active;
    }

    private function extractServices(Module $module): array
    {
        // We rely on the module's internal manifest mapping
        // This is a simplified extraction for the cache
        return $module->Settings['services'] ?? [];
    }

    private function extractConfig(Module $module): array
    {
        $config = [];
        // Pre-resolve all default config variables
        if (!empty($module->ConfigVariables)) {
            foreach ($module->ConfigVariables as $var => $default) {
                // Try to resolve current value from layers
                $val = Module::getConfig($var, $module->InternalName, $this->appContext);
                $config[$var] = $val !== false ? $val : $default;
            }
        }
        return $config;
    }

    private function writeCache(array $registry): bool
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n// SPP Compiled Module Registry - DO NOT EDIT\n";
        $content .= "return " . var_export($registry, true) . ";\n";

        return (bool) file_put_contents($this->cacheFile, $content);
    }

    public static function getCachePath(string $appContext): string
    {
        return SPP_APP_DIR . SPP_DS . 'var' . SPP_DS . 'cache' . SPP_DS . 'modules_' . $appContext . '.php';
    }
}
