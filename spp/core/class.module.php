<?php

namespace SPP;

use SPP\Exceptions\DuplicateModuleException;
use SPP\Core\Interfaces\ModuleInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * class \SPP\Module
 * Defines a new module in Satya Portal Pack.
 *
 * Modernized for PHP 8+ — fully backward compatible.
 *
 * @author Satya Prakash Shukla
 */
class Module extends \SPP\SPPObject implements ModuleInterface
{
    /**
     * Allowed magic set/get properties (kept for backward compatibility).
     * These names are intentionally the same as the legacy class.
     *
     * @var array<string>
     */
    protected array $_setprops = [
        'PublicName',
        'PublicDesc',
        'InternalName',
        'Version',
        'InstallScript',
        'UninstallScript',
        'ModuleGroup',
        'ModuleCategory',
        'IncludeFiles',
        'Dependencies',
        'ModPath',
        'ConfigFile',
        'ConfigVariables',
        'Settings',
        'Installation',
        'RuntimeBridgeConfig',
        'Routes',
        'ServiceProvider'
    ];

    /** @var array<string> */
    protected array $_getprops = [
        'PublicName',
        'PublicDesc',
        'InternalName',
        'InstallScript',
        'UninstallScript',
        'ModuleDir',
        'Version',
        'IncludeFiles',
        'Dependencies',
        'ModPath',
        'ConfigFile',
        'ModuleGroup',
        'ModuleCategory',
        'ConfigVariables',
        'Settings',
        'Installation',
        'RuntimeBridgeConfig',
        'Routes',
        'ServiceProvider'
    ];

    /**
     * Declared authorized module assets directory list routed statically.
     * @var array
     */
    public array $Assets = [];

    /**
     * Module category grouping (e.g. Core Required, Core Optional, App Modules)
     * @var string
     */
    public string $ModuleCategory = '';

    /**
     * In-memory cache for configuration values to prevent duplicate XML I/O parsing.
     * @var array<string, array<string, mixed>>
     */
    protected static array $configCache = [];

    /**
     * Module origin type (system | user)
     * @var string
     */
    public string $ModuleType = 'user';

    /** @var bool Guard to prevent redundant full scans */
    private static bool $allModulesLoaded = false;

    /** @var array<string> Search roots for system modules */
    private static array $_system_module_roots = ['', 'spp', 'contrib', 'school', 'custom'];

    /** @var array $Dependencies Stores dependencies requested by the module */
    public $Dependencies = [];

    /**
     * Adds a new search root for system modules.
     * @param string $path Relative path from SPP_MODULES_DIR
     */
    public static function addModuleRoot(string $path): void
    {
        $path = trim($path, '/\\');
        if (!in_array($path, self::$_system_module_roots)) {
            self::$_system_module_roots[] = $path;
        }
    }

    /** @var array<string, array> Global manifest file cache */
    private static array $manifestFileCache = [];

    /** @var array<string, array> Individual module manifest data cache */
    private static array $moduleManifestCache = [];

    /** @var array Runtime bridge configuration */
    public array $RuntimeBridgeConfig = [];

    /**
     * Disables a module by removing it from the registry.
     * This prevents isEnabled() checks from returning true.
     */
    public static function disableModule(string $name): void
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $name);
        \SPP\Registry::register('__mods=>' . $name, false);
        \SPP\Registry::register('__modobj=>' . $name, null);
    }
    /**
     * Module constructor.
     *
     * Accepts path to module manifest (module.xml or module.yml).
     *
     * @param string $file Path to module manifest
     * @throws \SPP\SPPException
     */
    public function __construct(string $file)
    {
        $this->ModPath = dirname($file);

        if (isset(self::$moduleManifestCache[$file])) {
            $this->mapManifestArray(self::$moduleManifestCache[$file]);
            return;
        }

        if (!file_exists($file)) {
            throw new \SPP\SPPException("Module manifest not found: {$file}");
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'yml' || $ext === 'yaml') {
            $this->readYaml($file);
        } else {
            throw new \SPP\SPPException("Unsupported manifest format (YAML only): {$file}");
        }
    }

    /**
     * Read module definition from YAML manifest.
     *
     * Note: legacy code mixed YAML and XML parsing — this method keeps behavior
     * consistent: it looks for top-level 'module' or 'modules' structure.
     *
     * @param string $file
     * @return void
     * @throws \SPP\SPPException
     */
    private function readYaml(string $file): void
    {
        $parsed = Yaml::parseFile($file);

        // YAML structure might vary; support a couple legacy-friendly shapes.
        // Typical expected shapes:
        //  modules:
        //    - module: { name: ..., version: ..., pubname: ..., ... }
        // or
        //  module:
        //    name: ...
        $moduleData = null;
        if (isset($parsed['module']) && is_array($parsed['module'])) {
            $moduleData = $parsed['module'];
        } elseif (isset($parsed['modules']) && is_array($parsed['modules'])) {
            // take first 'module' entry (legacy modules.yml may have many)
            $first = reset($parsed['modules']);
            if (is_array($first) && isset($first['module'])) {
                $moduleData = $first['module'];
            } else {
                $moduleData = $first;
            }
        } elseif (isset($parsed['name']) || isset($parsed['modname'])) {
            $moduleData = $parsed;
        } else {
            throw new \SPP\SPPException("Unexpected YAML module format in {$file}");
        }

        $this->ModPath = dirname($file);

        // Map fields using same keys as XML parser for compatibility
        $this->mapManifestArray($moduleData);
        self::$moduleManifestCache[$file] = $moduleData;
    }



    /**
     * Returns the raw settings definitions from the manifest.
     *
     * @return array
     */
    public function getSettingsDefinition(): array
    {
        return $this->Settings ?? [];
    }

    /**
     * Common helper: maps a parsed manifest associative array to module properties.
     *
     * Keeps legacy keys names (name, version, pubname, pubdesc, modgroup, config, includes, deps).
     *
     * @param array<mixed> $arr
     * @return void
     * @throws \SPP\SPPException
     */
    private function mapManifestArray(array $arr): void
    {
        $this->Dependencies = [];
        $this->IncludeFiles = [];

        foreach ($arr as $key => $val) {
            switch (strtolower($key)) {
                case 'name':
                    $this->InternalName = (string) $val;
                    break;
                case 'version':
                    $this->Version = (string) $val;
                    break;
                case 'pubname':
                case 'publicname':
                    $this->PublicName = (string) $val;
                    break;
                case 'modgroup':
                case 'modulegroup':
                    $this->ModuleGroup = (string) $val;
                    break;
                case 'category':
                case 'modulecategory':
                case 'module_category':
                    $this->ModuleCategory = (string) $val;
                    break;
                case 'pubdesc':
                case 'publicdesc':
                    $this->PublicDesc = (string) $val;
                    break;
                case 'config':
                    $this->ConfigFile = (string) $val;
                    break;
                case 'config_variables':
                case 'config_defaults':
                    $this->ConfigVariables = (array) $val;
                    break;
                case 'includes':
                    // includes may be a single include entry or an array
                    $includes = (array) $val;
                    // If YAML/XML structure nests 'include' key, handle it
                    if (isset($includes['include'])) {
                        $this->IncludeFiles = (array) $includes['include'];
                    } else {
                        $this->IncludeFiles = $includes;
                    }
                    break;
                case 'deps':
                case 'dependencies':
                    $deps = (array) $val;
                    if (isset($deps['depends'])) {
                        $this->Dependencies = (array) $deps['depends'];
                    } else {
                        $this->Dependencies = $deps;
                    }
                    break;
                case 'installation':
                    $this->Installation = (array) $val;
                    break;
                case 'runtime_bridge':
                case 'bridge':
                    $this->RuntimeBridgeConfig = (array) $val;
                    break;
                case 'services':
                    $this->registerServices((array) $val);
                    break;
                case 'settings':
                    $this->Settings = (array) $val;
                    break;
                case 'routes':
                    $this->Routes = (array) $val;
                    break;
                case 'assets':
                    $this->Assets = (array) $val;
                    break;
                default:
                    // Ignore unknown keys (keep robust)
                    break;
            }
        }

        // Merge module-specific routes from etc/routes.yml
        $routesYml = $this->ModPath . SPP_DS . 'etc' . SPP_DS . 'routes.yml';
        if (file_exists($routesYml)) {
            try {
                $rdata = Yaml::parseFile($routesYml);
                if (is_array($rdata)) {
                    if (isset($rdata['routes']) && is_array($rdata['routes'])) {
                        // Support list format
                        foreach ($rdata['routes'] as $r) {
                            if (isset($r['path'])) {
                                $this->Routes[ltrim($r['path'], '/')] = $r;
                            }
                        }
                    } elseif (isset($rdata['pages']) && is_array($rdata['pages'])) {
                        // Support map format
                        $this->Routes = array_merge($this->Routes, $rdata['pages']);
                    } else {
                        // Assume direct map
                        $this->Routes = array_merge($this->Routes, $rdata);
                    }
                }
            } catch (\Exception $e) {
                // Silently ignore or log parsing error
            }
        }

        // Basic validation: internal name must be set
        if (empty($this->_attributes['InternalName'])) {
            throw new \SPP\SPPException('Module manifest missing "name" (InternalName).');
        }
    }

    /**
     * Registers services into the current application container.
     */
    private function registerServices(array $services): void
    {
        $app = \SPP\App::getApp();
        foreach ($services as $abstract => $concrete) {
            $shared = true; // Default to singleton for services
            if (is_array($concrete)) {
                $shared = $concrete['shared'] ?? true;
                $concrete = $concrete['class'] ?? $concrete['concrete'] ?? null;
            }
            if ($concrete) {
                $app->bind($abstract, $concrete, $shared);
            }
        }
    }

    /**
     * Diagnostic helper to get a framework-level setting from global-settings.yml.
     * This avoids requiring a full application context for basic checks (e.g. debug mode).
     *
     * @param string $root Section root (e.g. 'settings')
     * @param string $key  Variable key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function getGlobalConfig(string $root, string $key, mixed $default = null): mixed
    {
        $path = (defined('SPP_ETC_DIR') ? SPP_ETC_DIR : __DIR__ . '/../etc') . '/global-settings.yml';
        if (!file_exists($path)) {
            return $default;
        }

        try {
            $data = Yaml::parseFile($path);
            return $data[$root][$key] ?? $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Returns true if the module is marked as compulsory in global-settings.yml.
     *
     * @param string $modname
     * @return bool
     */
    public static function isCompulsory(string $modname): bool
    {
        $compulsory = self::getGlobalConfig('settings', 'compulsory_modules', []);
        return in_array($modname, $compulsory);
    }

    /**
     * Gets a config variable for the module.
     *
     * Resolution order (highest to lowest priority):
     *  1. In-memory cache
     *  2. Canonical per-app YAML: spp/etc/apps/<app>/modsconf/<modname>/config.yml
     *  3. Module's own bundled config (module.xml → <config> tag)
     *  4. Legacy YAML: etc/settings/modules/<modname>/config.yml
     *  5. Legacy XML: modsconf/<modname>/config.xml
     *
     * @param string $varname
     * @param string $modname
     * @param string|null $appname Optional app context
     * @return mixed
     * @throws \SPP\SPPException
     */
    public static function getConfig(string $varname, string $modname, ?string $appname = null): mixed
    {
        // Sanitize varname and modname against injection & traversal
        $varname = str_replace(["'", '"'], '', $varname);
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = $appname ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname) : \SPP\Scheduler::getContext();
        $cacheKey = $appname . '::' . $modname . '::' . $varname;

        if (isset(self::$configCache[$cacheKey])) {
            return self::$configCache[$cacheKey];
        }

        // --- Layer 1: Check isolated per-app YAML config (Modern TOP priority) ---
        if ($appname) {
            $modsConfDir = self::getEffectiveModsConfDir($modname, $appname);
            $isolatedConf = $modsConfDir . SPP_DS . $modname . SPP_DS . 'config.yml';
            if (file_exists($isolatedConf)) {
                $yamlData = Yaml::parseFile($isolatedConf);
                $val = $yamlData['variables'][$varname] ?? ($yamlData[$varname] ?? null);
                if ($val !== null) {
                    $result = $val;
                    self::$configCache[$cacheKey] = $result;
                    return $result;
                }
            }
        }

        // --- Layer 1.1: Fallback to 'default' isolated config if context is not 'default' ---
        if ($appname && $appname !== 'default') {
            $defaultModsConfDir = self::getEffectiveModsConfDir($modname, 'default');
            $defaultConf = $defaultModsConfDir . SPP_DS . $modname . SPP_DS . 'config.yml';
            if (file_exists($defaultConf)) {
                $yamlData = Yaml::parseFile($defaultConf);
                $val = $yamlData['variables'][$varname] ?? ($yamlData[$varname] ?? null);
                if ($val !== null) {
                    self::$configCache[$cacheKey] = $val;
                    return $val;
                }
            }
        }

        // --- Layer 2: Try /etc/<appname>/modsconf/config.yml (Global App Config) ---
        $appname = $appname ?: \SPP\Scheduler::getContext();
        if ($appname) {
            $appGlobalConf = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'modsconf' . SPP_DS . 'config.yml';
            if (file_exists($appGlobalConf)) {
                $appYaml = Yaml::parseFile($appGlobalConf);
                if (isset($appYaml[$modname]['variables'][$varname])) {
                    $val = $appYaml[$modname]['variables'][$varname];
                    self::$configCache[$cacheKey] = $val;
                    return $val;
                } elseif (isset($appYaml[$modname][$varname])) {
                    $val = $appYaml[$modname][$varname];
                    self::$configCache[$cacheKey] = $val;
                    return $val;
                }
            }
        }

        // --- Layer 3: Global system service fallback (<modname>.yml) ---
        $globalServiceConf = SPP_ETC_DIR . SPP_DS . $modname . '.yml';
        if (file_exists($globalServiceConf)) {
            $serviceYaml = Yaml::parseFile($globalServiceConf);
            $val = $serviceYaml['variables'][$varname] ?? ($serviceYaml[$varname] ?? null);
            if ($val !== null) {
                $result = $val;
                self::$configCache[$cacheKey] = $result;
                return $result;
            }
        }

        // --- Layer 4: Try module's own bundled config (from manifest <config> tag) ---
        $modpath = \SPP\Registry::get('__mods=>' . $modname);
        if ($modpath !== false) {
            $manifestFile = $modpath . SPP_DS . 'module.yml';
            if (file_exists($manifestFile)) {
                $yml = Yaml::parseFile($manifestFile);
                $configRelPath = $yml['module']['config'] ?? null;
                if ($configRelPath) {
                    $cfgFile = $modpath . SPP_DS . $configRelPath;
                    if (file_exists($cfgFile)) {
                        $cfgData = Yaml::parseFile($cfgFile);
                        $val = $cfgData['variables'][$varname] ?? ($cfgData[$varname] ?? null);
                        if ($val !== null) {
                            self::$configCache[$cacheKey] = $val;
                            return $val;
                        }
                    }
                }
            }
        }

        $result = false;
        // --- Layer 5: Manifest Declarations (Final Fallback) ---
        $mod = \SPP\Registry::get('__modobj=>' . $modname);
        if ($mod) {
            $declared = $mod->ConfigVariables ?? [];
            if (in_array($varname, $declared) || isset($declared[$varname])) {
                $result = (string) ($declared[$varname] ?? "");
            }
        }

        self::$configCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Retrieves the entire configuration array for a module in a specific app.
     *
     * @param string $modname
     * @param string|null $appname
     * @return array
     */
    public static function getAppConfig(string $modname, ?string $appname = null): array
    {
        $yamlConfFile = self::getExpectedConfigPath($modname, $appname);
        if (file_exists($yamlConfFile)) {
            try {
                $data = Yaml::parseFile($yamlConfFile);
                return $data['variables'] ?? $data;
            } catch (\Exception $e) {
            }
        }
        return [];
    }

    /**
     * Persists a bulk configuration array for a module.
     *
     * @param string $modname
     * @param string $appname
     * @param array $config
     * @return bool
     */
    public static function saveAppConfig(string $modname, string $appname, array $config): bool
    {
        $yamlConfFile = self::getExpectedConfigPath($modname, $appname);
        $dir = dirname($yamlConfFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
        }

        $data = ['variables' => $config];
        $content = Yaml::dump($data, 4, 4);
        return file_put_contents($yamlConfFile, $content) !== false;
    }

    /**
     * Sets a config variable for the module, persisting it to the canonical
     * per-app YAML config file: spp/etc/apps/<app>/modsconf/<modname>/config.yml
     *
     * Creates the directory and file if they do not yet exist.
     * Invalidates the in-memory cache entry so subsequent getConfig() reads
     * reflect the new value immediately.
     *
     * @param string $varname Config variable name
     * @param mixed  $value   Value to store (will be cast to string in YAML)
     * @param string $modname Module internal name
     * @return void
     * @throws \SPP\SPPException
     */
    public static function setConfig(string $varname, mixed $value, string $modname, ?string $appname = null): void
    {
        $varname = str_replace(["'", '"'], '', $varname);
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);

        // Register schema from manifest before validation
        try {
            $mod = self::getModule($modname);
            $settingsDef = $mod->getSettingsDefinition();
            if (!empty($settingsDef)) {
                \SPP\SPPConfig::registerSchema("mod:{$modname}", $settingsDef);
            }
        } catch (\Exception $e) {
        }

        // Perform semantic validation
        \SPP\SPPConfig::validate($varname, $value, "mod:{$modname}");

        $yamlConfFile = self::getExpectedConfigPath($modname, $appname);

        // Load existing data or start fresh
        $yamlData = [];
        if (file_exists($yamlConfFile)) {
            $yamlData = Yaml::parseFile($yamlConfFile) ?? [];
        } else {
            $dir = dirname($yamlConfFile);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new \SPP\SPPException("Failed to create config directory: {$dir}");
                }
            }
        }

        // Store under the 'variables' key to match the getConfig() read convention
        if (!isset($yamlData['variables']) || !is_array($yamlData['variables'])) {
            $yamlData['variables'] = [];
        }
        $yamlData['variables'][$varname] = $value;

        file_put_contents($yamlConfFile, Yaml::dump($yamlData, 4, 4));

        // Invalidate cache so next getConfig() reflects the new value
        $context = $appname ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname) : \SPP\Scheduler::getContext();
        $cacheKey = $context . '::' . $modname . '::' . $varname;
        self::$configCache[$cacheKey] = (string) $value;
    }

    /**
     * Get config directory for module (app-level mods conf dir + module).
     *
     * @param string $modname
     * @return string
     */
    public static function getConfDir(string $modname, ?string $appname = null): string
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = $appname ?: \SPP\Scheduler::getContext();
        $dir = self::getEffectiveModsConfDir($modname, $appname);
        return $dir . SPP_DS . $modname;
    }

    /**
     * Returns the expected path for a module's YAML configuration file.
     *
     * @param string $modname
     * @param string|null $appname
     * @return string
     */
    public static function getExpectedConfigPath(string $modname, ?string $appname = null): string
    {
        $ctx = $appname ?? \SPP\Scheduler::getContext();
        try {
            $app = \SPP\App::getApp($ctx);
            $baseDir = $app->getModsConfDir();
            return $app->resolvePath($modname . "/config.yml", $baseDir);
        } catch (\Exception $e) {
            // Fallback to legacy structure if App cannot be fully initialized
            $path = SPP_APP_DIR . "/etc/apps/$ctx/modsconf/$modname/config.yml";
            return str_replace('\\', '/', $path);
        }
    }

    /**
     * Returns an array of candidate config file paths for a module/filename.
     *
     * Legacy code returned an array with two candidate files.
     *
     * @param string $modname
     * @param string $filename
     * @return array<string>
     */
    public static function getConfFile(string $modname, string $filename): array
    {
        $file = self::getConfDir($modname);
        $file .= SPP_DS . $filename;

        $legacyYaml = SPP_APP_DIR . SPP_DS . 'etc' . SPP_DS . 'modules' . SPP_DS . $modname . SPP_DS . 'config.yml';

        return [$file, $legacyYaml];
    }

    /**
     * Returns a Module object for given module name by loading module.xml from registry path.
     *
     * @param string $modname
     * @return \SPP\Module
     * @throws \SPP\SPPException
     */
    public static function getModule(string $modname): \SPP\Module
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $modpath = \SPP\Registry::get('__mods=>' . $modname);
        if ($modpath === false) {
            throw new \SPP\SPPException("Module not registered: {$modname}");
        }

        $manifest = $modpath . SPP_DS . 'module.yml';
        if (!file_exists($manifest)) {
            $manifest = $modpath . SPP_DS . 'module.xml';
        }

        if (!file_exists($manifest)) {
            throw new \SPP\SPPException("Module manifest not found for '{$modname}' at {$modpath}");
        }

        return new \SPP\Module($manifest);
    }

    /**
     * Scans modules directory for available modules (legacy helper).
     *
     * @return array<string>
     */
    public static function scanModules(): array
    {
        return SPPFS::findFile('module.yml', SPP_MODULES_DIR);
    }

    public function getPath(): string
    {
        return $this->ModPath;
    }

    /**
     * Includes required files declared in module manifest, runs legacy mod-init, 
     * and triggers ServiceProvider if declared.
     *
     * @return void
     */
    public function includeFiles(): void
    {
        $arr = (array) ($this->IncludeFiles ?? []);
        $realModPath = realpath($this->ModPath);

        foreach ($arr as $file) {
            $path = $this->ModPath . SPP_DS . $file;
            $realPath = realpath($path);

            if ($realPath !== false && str_starts_with($realPath, $realModPath) && file_exists($realPath)) {
                require_once $realPath;
            }
        }

        $initFile = $this->ModPath . SPP_DS . 'modinit.php';
        if (file_exists($initFile)) {
            require_once $initFile;
        }

        $eventsDir = $this->ModPath . SPP_DS . 'events';
        if (file_exists($eventsDir) && is_dir($eventsDir)) {
            // Register event directories using legacy API
            \SPP\SPPEvent::scanAndRegisterDirs($eventsDir);
        }

        // Boot Service Provider if declared
        if (isset($this->ServiceProvider) && !empty($this->ServiceProvider)) {
            $providerClass = $this->ServiceProvider;
            if (class_exists($providerClass)) {
                $provider = new $providerClass();
                if ($provider instanceof \SPP\Core\Interfaces\ServiceProviderInterface) {
                    $provider->register();
                    // Store provider to boot later if needed, but for SPP we'll boot immediately
                    // unless a formal application lifecycle handles it. For now, boot immediately.
                    $provider->boot();
                }
            }
        }
    }

    /**
     * Registers this module in the registry (path only).
     *
     * @return void
     * @throws DuplicateModuleException
     */
    public function register(): void
    {
        if (\SPP\Registry::get('__mods=>' . $this->InternalName) === false) {
            \SPP\Registry::register('__mods=>' . $this->InternalName, $this->ModPath);
            \SPP\Registry::register('__modobj=>' . $this->InternalName, $this);

            // Register semantic validation schema if defined
            if (!empty($this->Settings)) {
                \SPP\SPPConfig::registerSchema('mod:' . $this->InternalName, $this->Settings);
            }

            // Automatically scan and register authorized asset routing routes mapping on startup
            if (!empty($this->Assets)) {
                $assetDirs = is_array($this->Assets['directories'] ?? null) ? $this->Assets['directories'] : (array) $this->Assets;
                foreach ($assetDirs as $aDir) {
                    if (is_string($aDir)) {
                        $routeKey = 'modasset/' . strtolower($this->InternalName);
                        \SPP\Registry::register('__asset_routes=>' . $routeKey, $this->ModPath . SPP_DS . trim($aDir, '/'));
                        break; // Only map the primary asset directory to the root modasset/<modname> route
                    }
                }
            }
        }
    }

    /**
     * Finds the absolute path to a module's manifest file (module.yml or module.xml).
     * Correctly handles nested directory structures (e.g. spp/modules/spp/modname).
     *
     * @param string $modname Internal name of the module
     * @param string $type Origin type ('system' or 'user')
     * @param string|null $appname App context for user modules
     * @return string|null
     */
    public static function findManifestPath(string $modname, string $type = 'system', ?string $appname = null): ?string
    {
        $possible = ['module.yml', 'module.yaml'];
        $appname = $appname ?: \SPP\Scheduler::getContext();

        if ($type === 'system') {
            $checkPaths = [];
            foreach (self::$_system_module_roots as $sub) {
                $checkPaths[] = SPP_MODULES_DIR . SPP_DS . ($sub ? $sub . SPP_DS : '') . $modname;
            }

            foreach ($checkPaths as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                foreach ($possible as $m) {
                    if (file_exists($dir . SPP_DS . $m)) {
                        return $dir . SPP_DS . $m;
                    }
                }
            }
        } else {
            // User/App modules
            $userDir = SPP_APP_DIR . SPP_DS . 'modules' . SPP_DS . $appname . SPP_DS . $modname;
            if (is_dir($userDir)) {
                foreach ($possible as $m) {
                    if (file_exists($userDir . SPP_DS . $m)) {
                        return $userDir . SPP_DS . $m;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Returns true if module is registered.
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return \SPP\Registry::get('__mods=>' . $this->InternalName) !== false;
    }

    /**
     * Loads all active modules for current context.
     *
     * Modern flow:
     *  - Loads original app and system modules.
     *  - Loads user/system modules from /etc/modules/<appname>/ (new).
     *
     * @return void
     * @throws \SPP\SPPException
     */
    public static function loadAllModules(): void
    {
        $appname = \SPP\Scheduler::getContext();

        // Track loaded modules per app context to avoid redundant work
        // but ensure services are registered for every app instance.
        static $loadedContexts = [];
        if (isset($loadedContexts[$appname])) {
            return;
        }
        $loadedContexts[$appname] = true;

        // --- Phase 1: Try Compiled Cache (High Performance) ---
        if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
            $cacheFile = \SPP\Core\ModuleCompiler::getCachePath($appname);
            if (file_exists($cacheFile)) {
                $compiled = require $cacheFile;
                if (is_array($compiled)) {
                    foreach ($compiled as $name => $data) {
                        self::initFromCache($name, $data);
                    }
                    self::$allModulesLoaded = true;
                    return;
                }
            }
        }

        // --- Phase 2: Runtime Topological Boot (Debug Mode) ---
        // If not using the compiled cache, we rely on the compiler dynamically
        // to discover and topologically sort modules to ensure dependencies are honored.
        $compiler = new \SPP\Core\ModuleCompiler($appname);
        try {
            $registry = $compiler->compileToArray();
            foreach ($registry as $name => $data) {
                self::initFromCache($name, $data);
            }
        } catch (\SPP\Exceptions\MissingDependencyException $e) {
            throw new \SPP\SPPException("Module Boot Error: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \SPP\SPPException("Failed to boot modules: " . $e->getMessage());
        }

        self::$allModulesLoaded = true;
    }

    /**
     * Rapid initialization of a module from compiled cache data.
     */
    private static function initFromCache(string $name, array $data): void
    {
        \SPP\Registry::register('__mods=>' . $name, $data['path']);

        $manifestPath = $data['path'] . SPP_DS . 'module.yml';
        if (!file_exists($manifestPath)) {
            $manifestPath = $data['path'] . SPP_DS . 'module.xml';
        }

        $module = new self($manifestPath);
        $module->ModuleType = $data['type'];

        \SPP\Registry::register('__modobj=>' . $name, $module);

        if (!empty($data['services'])) {
            $module->registerServices($data['services']);
        }

        foreach ($data['includes'] as $file) {
            $path = $data['path'] . SPP_DS . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }

        $initFile = $data['path'] . SPP_DS . 'modinit.php';
        if (file_exists($initFile)) {
            require_once $initFile;
        }

        $eventsDir = $data['path'] . SPP_DS . 'events';
        if (is_dir($eventsDir)) {
            \SPP\SPPEvent::scanAndRegisterDirs($eventsDir);
        }
    }


    /**
     * Helper to load modules from a specific manifest file.
     *
     * @param string $file        Path to modules.xml or modules.yml
     * @param string $defaultType Default module type (system/user)
     * @return void
     * @throws \SPP\SPPException
     */
    private static function loadModulesFromManifest(string $file, string $defaultType): void
    {
        $appname = \SPP\Scheduler::getContext();
        if (!file_exists($file)) {
            return;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mods = [];

        try {
            if (isset(self::$manifestFileCache[$file])) {
                $mods = self::$manifestFileCache[$file];
            } else {
                if ($ext === 'yml' || $ext === 'yaml') {
                    $parsed = Yaml::parseFile($file);
                    $mods = $parsed['modules'] ?? [];
                    self::$manifestFileCache[$file] = $mods;
                }
            }
        } catch (\Exception $e) {
            return;
        }

        $deadModules = [];

        foreach ($mods as $mod) {
            $modArr = (array) $mod;

            // Handle type and base direction resolution
            $type = $modArr['type'] ?? $defaultType;
            $status = $modArr['status'] ?? 'active';
            $name = $modArr['name'] ?? $modArr['modname'] ?? basename($modArr['modpath'] ?? ($modArr['path'] ?? ''));

            if ((string) $status !== 'active' && !self::isCompulsory($name)) {
                continue;
            }

            $path = $modArr['modpath'] ?? ($modArr['path'] ?? null);
            if (empty($path)) {
                continue;
            }

            // Normalize path separators
            if (SPP_DS !== '/') {
                $path = str_replace('/', SPP_DS, $path);
            }

            $manifestPath = null;
            $possibleManifests = ['module.yml', 'module.yaml', 'module.xml'];

            // Primary parent directory based on type
            $primaryDir = ($type === 'system')
                ? SPP_MODULES_DIR
                : SPP_APP_DIR . SPP_DS . 'modules' . SPP_DS . $appname;

            // Discovery logic for system modules (any-depth)
            if ($type === 'system') {
                $foundDir = null;
                if (is_dir(SPP_MODULES_DIR . SPP_DS . $path)) {
                    $foundDir = SPP_MODULES_DIR . SPP_DS . $path;
                } else {
                    // Try depth 2: e.g. spp/modules/spp/modname or spp/modules/contrib/modname
                    foreach (['spp', 'contrib', 'school', 'custom'] as $sub) {
                        if (is_dir(SPP_MODULES_DIR . SPP_DS . $sub . SPP_DS . $path)) {
                            $foundDir = SPP_MODULES_DIR . SPP_DS . $sub . SPP_DS . $path;
                            break;
                        }
                    }
                }

                if ($foundDir) {
                    foreach ($possibleManifests as $m) {
                        if (file_exists($foundDir . SPP_DS . $m)) {
                            $manifestPath = $foundDir . SPP_DS . $m;
                            break;
                        }
                    }
                }
            } else {
                // User/App modules
                foreach ($possibleManifests as $m) {
                    $testPath = $primaryDir . SPP_DS . $path . SPP_DS . $m;
                    if (file_exists($testPath)) {
                        $manifestPath = $testPath;
                        break;
                    }
                }
            }

            if (!$manifestPath) {
                $deadModules[] = $modArr['name'] ?? $modArr['modname'] ?? basename($path);
                continue;
            }

            $module = new \SPP\Module($manifestPath);
            // Safety Enforcement: Force system type if located in SPP_MODULES_DIR
            if (strpos(realpath($manifestPath), realpath(SPP_MODULES_DIR)) === 0) {
                $module->ModuleType = 'system';
            } else {
                $module->ModuleType = $type;
            }

            $module->register();
            $module->includeFiles();
        }

        // Cleanup dead references automatically
        // Only prune if we are reasonably sure the filesystem is healthy and the base directory exists
        if (!empty($deadModules) && is_dir(SPP_MODULES_DIR)) {
            self::pruneFromManifest($file, $deadModules);
        }
    }

    /**
     * Removes dead module references from a manifest file.
     *
     * @param string $file
     * @param array $moduleNames
     */
    private static function pruneFromManifest(string $file, array $moduleNames): void
    {
        if (!file_exists($file)) {
            return;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($ext === 'yml' || $ext === 'yaml') {
            $data = Yaml::parseFile($file);
            if (!isset($data['modules']) || !is_array($data['modules'])) {
                return;
            }

            $initialCount = count($data['modules']);
            $data['modules'] = array_filter($data['modules'], function ($m) use ($moduleNames) {
                $mArr = (array) $m;
                $name = $mArr['name'] ?? $mArr['modname'] ?? '';
                return !in_array($name, $moduleNames);
            });

            if (count($data['modules']) !== $initialCount) {
                file_put_contents($file, Yaml::dump($data, 4, 4));
            }
        }
    }

    /**
     * Returns true if a module is enabled (registered).
     *
     * @param string $mod
     * @return bool
     */
    public static function isEnabled(string $mod): bool
    {
        return \SPP\Registry::get('__mods=>' . $mod) !== false;
    }

    /**
     * Initializes and registers a module by name.
     */
    public static function loadModule(string $modname, string $type = 'system', ?string $appname = null): ?\SPP\Module
    {
        $manifestPath = self::findManifestPath($modname, $type, $appname);
        if (!$manifestPath) {
            return null;
        }

        $module = new self($manifestPath);
        $module->ModuleType = $type;
        $module->register();
        $module->includeFiles();
        return $module;
    }

    /**
     * Toggles a module's status in all known modules.xml and modules.yml files
     * (system-level and per-app) to keep them in sync.
     *
     * @param string $modname Module internal name
     * @param string $status  'active' or 'inactive'
     * @return array List of files that were modified
     * @throws \SPP\SPPException
     */
    public static function toggleModuleStatus(string $modname, string $status, ?string $appname = null): array
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $status = in_array($status, ['active', 'inactive']) ? $status : 'inactive';

        if ($status === 'inactive' && self::isCompulsory($modname)) {
            throw new \SPP\SPPException("Module '{$modname}' is compulsory and cannot be disabled.");
        }

        $updatedFiles = [];

        // Determine app context
        $appname = $appname ?: (\SPP\Scheduler::getContext() ?: ($_REQUEST['appname'] ?? 'default'));

        // 1. Identify all potential manifests for this app
        $candidates = [];
        $registries = self::getRegistryFiles($appname);
        foreach ($registries as $r) {
            $candidates[] = $r['file'];
        }
        $preferred = !empty($candidates) ? $candidates[0] : '';

        // 2. Identify type and location (for registration if missing)
        $modPath = \SPP\Registry::get('__mods=>' . $modname);
        $type = 'system'; // Default

        if (!$modPath) {
            $fullPath = self::findManifestPath($modname, 'system', $appname);
            if ($fullPath) {
                $modPath = dirname($fullPath);
                $type = 'system';
            }
        }

        if (!$modPath) {
            // Fallback discovery if not registered
            $fullPath = self::findManifestPath($modname, 'user', $appname);
            if ($fullPath) {
                $modPath = dirname($fullPath);
                $type = 'user';
            }
        }

        if (!$modPath) {
            throw new \SPP\SPPException("Module '{$modname}' could not be located in the filesystem.");
        }

        // 3. Try to update existing entries
        foreach ($candidates as $file) {
            // Normalize path
            $file = str_replace('\\', '/', $file);

            if (!file_exists($file)) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (($ext === 'yml' || $ext === 'yaml') && self::toggleInYaml($file, $modname, $status)) {
                $updatedFiles[] = $file;
            }
        }

        // 4. If not found in any file, append to the preferred one
        if (empty($updatedFiles) && $preferred !== '') {
            // Normalize path
            $preferred = str_replace('\\', '/', $preferred);

            if (!is_dir(dirname($preferred))) {
                mkdir(dirname($preferred), 0755, true);
            }

            $entry = [
                'name' => $modname,
                'path' => self::moduleRegistryPath((string) $modPath, $type, $appname),
                'status' => $status
            ];

            $data = file_exists($preferred) ? Yaml::parseFile($preferred) : ['modules' => []];
            if (!isset($data['modules']) || !is_array($data['modules'])) {
                $data['modules'] = [];
            }
            $data['modules'][] = $entry;

            $yml = Yaml::dump($data, 4, 4);
            file_put_contents($preferred, $yml);
            $updatedFiles[] = $preferred;
        }

        if ($status === 'active') {
            $configPath = self::ensureConfigForApp($modname, $appname);
            if ($configPath !== null) {
                $updatedFiles[] = $configPath;
            }
        }

        return $updatedFiles;
    }

    /**
     * Normalizes an absolute module path into the portable value stored in modules.yml.
     */
    private static function moduleRegistryPath(string $modPath, string $type, string $appname): string
    {
        $modPath = str_replace('\\', '/', $modPath);

        if ($type === 'system') {
            foreach (self::$_system_module_roots as $root) {
                $base = str_replace('\\', '/', realpath(SPP_MODULES_DIR . ($root ? SPP_DS . $root : '')) ?: (SPP_MODULES_DIR . ($root ? SPP_DS . $root : '')));
                $real = str_replace('\\', '/', realpath($modPath) ?: $modPath);
                if (str_starts_with($real, $base)) {
                    return trim(substr($real, strlen($base)), '/');
                }
            }
        } else {
            $base = str_replace('\\', '/', realpath(SPP_APP_DIR . SPP_DS . 'modules' . SPP_DS . $appname) ?: (SPP_APP_DIR . SPP_DS . 'modules' . SPP_DS . $appname));
            $real = str_replace('\\', '/', realpath($modPath) ?: $modPath);
            if (str_starts_with($real, $base)) {
                return trim(substr($real, strlen($base)), '/');
            }
        }

        return trim($modPath, '/');
    }

    /**
     * Ensures a per-app config.yml exists for a module and contains all declared defaults.
     *
     * Existing values are preserved so admin changes are not overwritten.
     *
     * @param string $modname Module internal name
     * @param string $appname Application context
     * @return string|null Path to the config file, or null when the module declares no defaults
     */
    public static function ensureConfigForApp(string $modname, string $appname): ?string
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname);
        if ($modname === '' || $appname === '') {
            return null;
        }

        $module = self::resolveModuleObject($modname, $appname);
        if (!$module instanceof \SPP\Module) {
            return null;
        }

        $defaults = self::extractConfigDefaults($module);
        if (empty($defaults)) {
            return null;
        }

        $modsConfDir = self::getEffectiveModsConfDir($modname, $appname);

        $configDir = $modsConfDir . SPP_DS . $modname;
        $configFile = $configDir . SPP_DS . 'config.yml';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $data = [];
        if (file_exists($configFile)) {
            $parsed = Yaml::parseFile($configFile);
            $data = is_array($parsed) ? $parsed : [];
        }

        if (!isset($data['variables']) || !is_array($data['variables'])) {
            $legacyValues = $data;
            $data = ['variables' => []];
            foreach ($legacyValues as $key => $value) {
                if ($key !== 'variables' && is_string($key)) {
                    $data['variables'][$key] = $value;
                }
            }
        }

        $changed = !file_exists($configFile);
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $data['variables'])) {
                $data['variables'][$key] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            file_put_contents($configFile, Yaml::dump($data, 4, 4));
        }

        foreach (array_keys($defaults) as $key) {
            unset(self::$configCache[$appname . '::' . $modname . '::' . $key]);
        }

        return $configFile;
    }

    /**
     * Builds default config variables from module.yml declarations.
     *
     * @param \SPP\Module $module
     * @return array<string,mixed>
     */
    private static function extractConfigDefaults(\SPP\Module $module): array
    {
        $defaults = [];

        foreach ((array) ($module->ConfigVariables ?? []) as $key => $value) {
            if (is_int($key) || ctype_digit((string) $key)) {
                $defaults[(string) $value] = '';
            } else {
                $defaults[(string) $key] = $value;
            }
        }

        foreach ((array) ($module->Settings ?? []) as $key => $definition) {
            if (!is_array($definition)) {
                if (!array_key_exists((string) $key, $defaults)) {
                    $defaults[(string) $key] = $definition;
                }
                continue;
            }

            $defaults[(string) $key] = array_key_exists('default', $definition)
                ? $definition['default']
                : ($defaults[(string) $key] ?? '');
        }

        return $defaults;
    }

    /**
     * Resolves a module object even when it is not already active in the registry.
     */
    private static function resolveModuleObject(string $modname, string $appname): ?\SPP\Module
    {
        $registered = \SPP\Registry::get('__modobj=>' . $modname);
        if ($registered instanceof \SPP\Module) {
            return $registered;
        }

        $manifest = self::findManifestPath($modname, 'system', $appname);
        if (!$manifest) {
            $manifest = self::findManifestPath($modname, 'user', $appname);
        }

        if (!$manifest) {
            return null;
        }

        $module = new \SPP\Module($manifest);
        if (strpos(realpath($manifest), realpath(SPP_MODULES_DIR)) === 0) {
            $module->ModuleType = 'system';
        } else {
            $module->ModuleType = 'user';
        }

        return $module;
    }

    /**
     * Migrates a modules.xml file to modules.yml in the same directory.
     *
     * Reads all <module> entries from the XML and writes them as a
     * structured YAML file. The XML file is left untouched.
     *
     * @param string $xmlFile  Absolute path to the source modules.xml
     * @param string $ymlFile  Absolute path to the target modules.yml
     * @return bool True if migration succeeded
     */
    public static function migrateXmlToYaml(string $xmlFile, string $ymlFile): bool
    {
        if (!file_exists($xmlFile)) {
            return false;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlFile);
        if ($xml === false) {
            return false;
        }

        $modules = [];
        foreach ($xml->module as $mod) {
            $entry = [];
            // Normalize: modules.xml uses <modname>, some use <name>
            $entry['modname'] = (string) ($mod->modname ?? $mod->name ?? '');
            if (empty($entry['modname'])) {
                continue;
            }
            $entry['path'] = (string) ($mod->modpath ?? $mod->path ?? '');
            $entry['status'] = (string) ($mod->status ?? 'active');
            // Preserve dependencies if present
            if (isset($mod->dependencies)) {
                $deps = [];
                foreach ($mod->dependencies->dependency as $dep) {
                    $deps[] = (string) $dep;
                }
                if (!empty($deps)) {
                    $entry['dependencies'] = $deps;
                }
            }
            $modules[] = $entry;
        }

        if (empty($modules)) {
            return false;
        }

        $data = ['modules' => $modules];

        if (!is_dir(dirname($ymlFile))) {
            mkdir(dirname($ymlFile), 0755, true);
        }

        file_put_contents($ymlFile, Yaml::dump($data, 4, 4));
        return true;
    }

    /**
     * Updates module status in an XML modules manifest using DOMDocument
     * to preserve formatting and comments.
     *
     * @param string $file    Path to modules.xml
     * @param string $modname Module name to find
     * @param string $status  New status value
     * @return bool True if the file was modified
     */
    private static function toggleInXml(string $file, string $modname, string $status): bool
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (!$dom->load($file)) {
            return false;
        }

        $modified = false;
        $found = false;
        $modules = $dom->getElementsByTagName('module');

        foreach ($modules as $moduleNode) {
            $nameNode = $moduleNode->getElementsByTagName('modname')->item(0);
            // Also try <name> tag (module.xml uses <name>, modules.xml uses <modname>)
            if (!$nameNode) {
                $nameNode = $moduleNode->getElementsByTagName('name')->item(0);
            }
            if (!$nameNode || $nameNode->textContent !== $modname) {
                continue;
            }
            $found = true;

            $statusNode = $moduleNode->getElementsByTagName('status')->item(0);
            if ($statusNode) {
                if ($statusNode->textContent !== $status) {
                    $statusNode->textContent = $status;
                    $modified = true;
                }
            } else {
                // Create <status> element if missing
                $newStatus = $dom->createElement('status', $status);
                $moduleNode->appendChild($newStatus);
                $modified = true;
                $dom->save($file);
            }
            break;
        }

        if ($modified) {
            $dom->save($file);
        }

        return $found;
    }

    /**
     * Updates module status in a YAML modules manifest.
     *
     * @param string $file    Path to modules.yml
     * @param string $modname Module name to find
     * @param string $status  New status value
     * @return bool True if the file was modified
     */
    private static function toggleInYaml(string $file, string $modname, string $status): bool
    {
        $parsed = Yaml::parseFile($file);
        if (!isset($parsed['modules']) || !is_array($parsed['modules'])) {
            return false;
        }

        $modified = false;
        $found = false;
        foreach ($parsed['modules'] as &$mod) {
            $modArr = (array) $mod;
            $name = $modArr['modname'] ?? ($modArr['name'] ?? null);
            if ($name === $modname) {
                $found = true;
                $currentStatus = $modArr['status'] ?? 'active';
                if ($currentStatus !== $status) {
                    $mod['status'] = $status;
                    $modified = true;
                }
                break;
            }
        }
        unset($mod);

        if ($modified) {
            file_put_contents($file, Yaml::dump($parsed, 4, 4));
        }

        return $found;
    }

    /**
     * Returns all config variables for a module as an associative array.
     *
     * Resolution order:
     *  1. Canonical per-app YAML config
     *  2. Module's bundled config (XML)
     *  3. Legacy YAML in app settings
     *  4. Legacy XML modsconf
     *
     * @param string $modname Module internal name
     * @return array ['variables' => [...], 'source' => string]
     */
    public static function getAllConfig(string $modname): array
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = \SPP\Scheduler::getContext();

        // 1. Start with per-app logic (Strict Separation)
        $res = self::getAllConfigForApp($modname, $appname);
        $variables = $res['variables'] ?? [];
        $source = $res['source'] ?? '';

        if (!empty($variables)) {
            return ['variables' => $variables, 'source' => $source];
        }

        // 2. Module's bundled config XML (Fallback)
        $modpath = \SPP\Registry::get('__mods=>' . $modname);
        if ($modpath !== false) {
            $modManifest = $modpath . SPP_DS . 'module.xml';
            if (file_exists($modManifest)) {
                $xml = simplexml_load_file($modManifest);
                if ($xml !== false) {
                    $arr = (array) ($xml->xpath('/module')[0] ?? []);
                    if (!empty($arr['config'])) {
                        $cfgFile = $modpath . SPP_DS . $arr['config'];
                        if (file_exists($cfgFile)) {
                            $ext = strtolower(pathinfo($cfgFile, PATHINFO_EXTENSION));
                            if ($ext === 'yml' || $ext === 'yaml') {
                                $cfgData = Yaml::parseFile($cfgFile);
                                $variables = $cfgData['variables'] ?? $cfgData ?? [];
                            } else {
                                $cfgXml = simplexml_load_file($cfgFile);
                                if ($cfgXml !== false) {
                                    $varNodes = $cfgXml->xpath('/config/variables/variable');
                                    foreach ($varNodes as $vn) {
                                        $variables[(string) $vn->name] = (string) $vn->value;
                                    }
                                }
                            }
                            $source = $cfgFile;
                            return ['variables' => $variables, 'source' => $source];
                        }
                    }
                }
            }
        }

        // 3. Legacy YAML in app settings
        if (defined('SPP_APP_DIR')) {
            $legacyYaml = SPP_APP_DIR . SPP_DS . 'etc' . SPP_DS . 'settings' . SPP_DS . 'modules' . SPP_DS . $modname . SPP_DS . 'config.yml';
            if (file_exists($legacyYaml)) {
                $yamlData = Yaml::parseFile($legacyYaml);
                $variables = $yamlData['variables'] ?? $yamlData ?? [];
                $source = $legacyYaml;
                return ['variables' => $variables, 'source' => $source];
            }
        }

        // 4. Legacy XML modsconf
        try {
            $proc = \SPP\Scheduler::getActiveProc();
            $confXmlFile = $proc->getModsConfDir() . SPP_DS . $modname . SPP_DS . 'config.xml';
            if (file_exists($confXmlFile)) {
                $cfgXml = simplexml_load_file($confXmlFile);
                if ($cfgXml !== false) {
                    $varNodes = $cfgXml->xpath('/config/variables/variable');
                    foreach ($varNodes as $vn) {
                        $variables[(string) $vn->name] = (string) $vn->value;
                    }
                }
                $source = $confXmlFile;
            }
        } catch (\Throwable $e) {
        }

        return ['variables' => $variables, 'source' => $source];
    }

    /**
     * Returns the raw content of a module's config file for direct editing.
     *
     * @param string $modname Module internal name
     * @return array ['content' => string, 'path' => string, 'format' => string]
     */
    public static function getRawConfig(string $modname): array
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);

        // Search for config file in priority order
        $candidates = [];

        // 1. Canonical per-app YAML
        try {
            $proc = \SPP\Scheduler::getActiveProc();
            $candidates[] = $proc->getModsConfDir() . SPP_DS . $modname . SPP_DS . 'config.yml';
        } catch (\Throwable $e) {
        }

        // 2. Module's bundled config (resolve from module.xml)
        $modpath = \SPP\Registry::get('__mods=>' . $modname);
        if ($modpath !== false) {
            $modManifest = $modpath . SPP_DS . 'module.xml';
            if (file_exists($modManifest)) {
                $xml = simplexml_load_file($modManifest);
                if ($xml !== false) {
                    $arr = (array) ($xml->xpath('/module')[0] ?? []);
                    if (!empty($arr['config'])) {
                        $candidates[] = $modpath . SPP_DS . $arr['config'];
                    }
                }
            }
        }

        // 3. Legacy paths
        try {
            $proc = \SPP\Scheduler::getActiveProc();
            $candidates[] = $proc->getModsConfDir() . SPP_DS . $modname . SPP_DS . 'config.xml';
        } catch (\Throwable $e) {
        }

        if (defined('SPP_APP_DIR')) {
            $candidates[] = SPP_APP_DIR . SPP_DS . 'etc' . SPP_DS . 'settings' . SPP_DS . 'modules' . SPP_DS . $modname . SPP_DS . 'config.yml';
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return [
                    'content' => file_get_contents($path),
                    'path' => $path,
                    'format' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                ];
            }
        }

        return ['content' => '', 'path' => '', 'format' => 'yml'];
    }

    /**
     * Returns all config variables for a module within a specific app context.
     * Bypasses the Scheduler — works with direct path resolution.
     *
     * @param string $modname Module internal name
     * @param string $appname Application name (e.g. 'default', 'demo', 'sppadmin')
     * @return array ['variables' => [...], 'source' => string]
     */
    public static function getAllConfigForApp(string $modname, string $appname): array
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname);
        $variables = [];
        $source = '';

        $modObj = self::resolveModuleObject($modname, $appname);
        $type = ($modObj instanceof \SPP\Module) ? $modObj->ModuleType : 'system';

        $modsConfDir = self::getEffectiveModsConfDir($modname, $appname);

        if ($modsConfDir !== '') {
            // 1. Canonical per-app YAML
            $yamlConfFile = $modsConfDir . SPP_DS . $modname . SPP_DS . 'config.yml';
            if (file_exists($yamlConfFile)) {
                $yamlData = Yaml::parseFile($yamlConfFile);
                $variables = $yamlData['variables'] ?? $yamlData ?? [];
                $source = $yamlConfFile;
                return ['variables' => $variables, 'source' => $source];
            }

            // 2. Canonical per-app XML
            $xmlConfFile = $modsConfDir . SPP_DS . $modname . SPP_DS . 'config.xml';
            if (file_exists($xmlConfFile)) {
                $cfgXml = simplexml_load_file($xmlConfFile);
                if ($cfgXml !== false) {
                    $varNodes = $cfgXml->xpath('/config/variables/variable');
                    foreach ($varNodes as $vn) {
                        $variables[(string) $vn->name] = (string) $vn->value;
                    }
                }
                $source = $xmlConfFile;
                return ['variables' => $variables, 'source' => $source];
            }
        }

        // 3. Module's bundled config (from manifest)
        $modpath = ($modObj instanceof \SPP\Module) ? $modObj->ModPath : \SPP\Registry::get('__mods=>' . $modname);
        if ($modpath !== false) {
            $manifestFiles = [$modpath . SPP_DS . 'module.yml', $modpath . SPP_DS . 'module.xml'];
            foreach ($manifestFiles as $modManifest) {
                if (file_exists($modManifest)) {
                    $ext = strtolower(pathinfo($modManifest, PATHINFO_EXTENSION));
                    $configRelPath = null;
                    if ($ext === 'yml' || $ext === 'yaml') {
                        $yml = Yaml::parseFile($modManifest);
                        $configRelPath = $yml['module']['config'] ?? null;
                    } else {
                        $xml = simplexml_load_file($modManifest);
                        if ($xml !== false) {
                            $arr = (array) ($xml->xpath('/module')[0] ?? []);
                            $configRelPath = $arr['config'] ?? null;
                        }
                    }

                    if ($configRelPath) {
                        $cfgFile = $modpath . SPP_DS . $configRelPath;
                        if (file_exists($cfgFile)) {
                            $cfgExt = strtolower(pathinfo($cfgFile, PATHINFO_EXTENSION));
                            if ($cfgExt === 'yml' || $cfgExt === 'yaml') {
                                $cfgData = Yaml::parseFile($cfgFile);
                                $foundVars = $cfgData['variables'] ?? $cfgData ?? [];
                                $variables = array_merge($variables, $foundVars);
                                $source = $cfgFile;
                            } else {
                                $cfgXml2 = simplexml_load_file($cfgFile);
                                if ($cfgXml2 !== false) {
                                    $varNodes = $cfgXml2->xpath('/config/variables/variable');
                                    foreach ($varNodes as $vn) {
                                        $variables[(string) $vn->name] = (string) $vn->value;
                                    }
                                    $source = $cfgFile;
                                }
                            }
                        }
                    }
                    if (!empty($variables)) {
                        break;
                    }
                }
            }
        }

        // 4. Manifest Declarations (Merged with actual values)
        if ($modObj instanceof \SPP\Module) {
            $declared = $modObj->ConfigVariables ?? [];
            if (!empty($declared)) {
                $merged = [];
                foreach ($declared as $k => $v) {
                    $key = is_numeric($k) ? (string) $v : (string) $k;
                    $merged[$key] = "";
                }
                // Merge any actually found variables over the defaults
                return ['variables' => array_merge($merged, $variables), 'source' => ($source ?: 'manifest')];
            }
        }

        return ['variables' => $variables, 'source' => $source];
    }

    /**
     * Returns raw config file content for a module within a specific app context.
     * Bypasses the Scheduler — works with direct path resolution.
     *
     * @param string $modname Module internal name
     * @param string $appname Application name
     * @return array ['content' => string, 'path' => string, 'format' => string]
     */
    public static function getRawConfigForApp(string $modname, string $appname): array
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname);

        $modObj = self::resolveModuleObject($modname, $appname);
        $type = ($modObj instanceof \SPP\Module) ? $modObj->ModuleType : 'system';

        $modsConfDir = self::getEffectiveModsConfDir($modname, $appname);

        if ($modsConfDir === '') {
            return ['content' => '', 'path' => '', 'format' => 'yml'];
        }

        $candidates = [];

        // 1. Per-app YAML
        $candidates[] = $modsConfDir . SPP_DS . $modname . SPP_DS . 'config.yml';
        // 2. Per-app XML
        $candidates[] = $modsConfDir . SPP_DS . $modname . SPP_DS . 'config.xml';

        // 3. Module's bundled config
        $modpath = ($modObj instanceof \SPP\Module) ? $modObj->ModPath : \SPP\Registry::get('__mods=>' . $modname);
        if ($modpath !== false) {
            foreach (['module.yml', 'module.yaml', 'module.xml'] as $manifestName) {
                $modManifest = $modpath . SPP_DS . $manifestName;
                if (!file_exists($modManifest)) {
                    continue;
                }
                $ext = strtolower(pathinfo($modManifest, PATHINFO_EXTENSION));
                if ($ext === 'yml' || $ext === 'yaml') {
                    $manifest = Yaml::parseFile($modManifest);
                    if (!empty($manifest['module']['config'])) {
                        $candidates[] = $modpath . SPP_DS . $manifest['module']['config'];
                    }
                } else {
                    $xml = simplexml_load_file($modManifest);
                    if ($xml !== false) {
                        $arr = (array) ($xml->xpath('/module')[0] ?? []);
                        if (!empty($arr['config'])) {
                            $candidates[] = $modpath . SPP_DS . $arr['config'];
                        }
                    }
                }
            }
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return [
                    'content' => file_get_contents($path),
                    'path' => $path,
                    'format' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                ];
            }
        }

        return ['content' => '', 'path' => '', 'format' => 'yml'];
    }

    /**
     * Saves a config variable for a module within a specific app context.
     * Bypasses the Scheduler.
     *
     * @param string $varname Variable name
     * @param mixed  $value   Value
     * @param string $modname Module internal name
     * @param string $appname Application name
     */
    public static function setConfigForApp(string $varname, mixed $value, string $modname, string $appname): void
    {
        $varname = str_replace(["'", '"'], '', $varname);
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname);

        $modObj = self::resolveModuleObject($modname, $appname);
        $type = ($modObj instanceof \SPP\Module) ? $modObj->ModuleType : 'system';

        $modsConfDir = self::getEffectiveModsConfDir($modname, $appname);

        $yamlConfFile = $modsConfDir . SPP_DS . $modname . SPP_DS . 'config.yml';

        $yamlData = [];
        if (file_exists($yamlConfFile)) {
            $yamlData = Yaml::parseFile($yamlConfFile) ?? [];
        } else {
            $dir = dirname($yamlConfFile);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new \SPP\SPPException("Failed to create config directory: {$dir}");
                }
            }
        }

        if (!isset($yamlData['variables']) || !is_array($yamlData['variables'])) {
            $legacyValues = $yamlData;
            $yamlData = ['variables' => []];
            foreach ($legacyValues as $key => $existingValue) {
                if ($key !== 'variables' && is_string($key)) {
                    $yamlData['variables'][$key] = $existingValue;
                }
            }
        }
        $yamlData['variables'][$varname] = $value;

        file_put_contents($yamlConfFile, Yaml::dump($yamlData, 4, 4));

        // Invalidate cache
        $appname = $appname ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname) : \SPP\Scheduler::getContext();
        $cacheKey = $appname . '::' . $modname . '::' . $varname;
        self::$configCache[$cacheKey] = (string) $value;
    }

    /**
     * Scans the module's installation requirements and returns a list of deltas
     * compared to the current database state.
     *
     * @return array
     */
    public function getInstallationDeltas(): array
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $deltas = [
            'tables' => [],
            'entities' => [],
            'sequences' => [],
            'config' => []
        ];

        $install = $this->Installation ?? [];

        // 1. Tables check
        $tables = $install['tables'] ?? [];
        foreach ($tables as $tname => $cols) {
            $tnameFull = \SPPMod\SPPDB\SPPDB::sppTable($tname);
            if (!$db->tableExists($tnameFull)) {
                $deltas['tables'][] = ['name' => $tname, 'status' => 'missing', 'columns' => $cols];
            } else {
                $missingCols = [];
                foreach ($cols as $col => $type) {
                    if ($col === 'PRIMARY KEY') {
                        continue;
                    } // Skip composite PK declaration
                    if (!$db->columnExists($tnameFull, $col)) {
                        $missingCols[$col] = $type;
                    }
                }
                if (!empty($missingCols)) {
                    $deltas['tables'][] = ['name' => $tname, 'status' => 'outdated', 'missing_columns' => $missingCols];
                }
            }
        }

        // 2. Entities check
        $entities = $install['entities'] ?? [];
        foreach ($entities as $entityClass) {
            if (class_exists($entityClass)) {
                try {
                    $reflection = new \ReflectionClass($entityClass);
                    if ($reflection->isSubclassOf('\\SPPMod\\SPPEntity\\SPPEntity')) {
                        // Check if the table for this entity exists
                        $entityName = $reflection->getShortName();
                        $tableName = $entityClass::getMetadata('table');
                        if (!$tableName) {
                            $tableName = strtolower($entityName) . 's';
                        }
                        $tableName = \SPPMod\SPPDB\SPPDB::sppTable($tableName);

                        if (!$db->tableExists($tableName)) {
                            $deltas['entities'][] = ['class' => $entityClass, 'status' => 'missing'];
                        }
                    }
                } catch (\Exception $e) {
                }
            } else {
                $deltas['entities'][] = ['class' => $entityClass, 'status' => 'not_found'];
            }
        }

        $sequences = $install['sequences'] ?? [];
        foreach ($sequences as $key => $val) {
            $seqName = is_int($key) ? $val : $key;
            $sDef = is_int($key) ? 1 : $val;

            if (!\SPPMod\SPPDB\SPPSequence::sequenceExists($seqName)) {
                $start = is_array($sDef) ? ($sDef['start'] ?? 1) : $sDef;
                $inc = is_array($sDef) ? ($sDef['increment'] ?? 1) : 1;

                $deltas['sequences'][] = [
                    'name' => $seqName,
                    'status' => 'missing',
                    'start' => (int) $start,
                    'increment' => (int) $inc
                ];
            }
        }

        return $deltas;
    }

    /**
     * Public wrapper for installation, returning success status.
     *
     * @param string|null $appname Optional application context
     * @return bool
     */
    public function install(?string $appname = null): bool
    {
        if ($appname) {
            \SPP\Scheduler::setContext($appname);
        }
        try {
            $this->runInstallation();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Executes the installation routines for the module incrementally.
     *
     * @return array Log of actions performed
     */
    public function runInstallation(): array
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $deltas = $this->getInstallationDeltas();
        $log = [];

        // 1. Process Tables
        foreach ($deltas['tables'] as $t) {
            $tableName = \SPPMod\SPPDB\SPPDB::sppTable($t['name']);
            if ($t['status'] === 'missing') {
                $db->createTableIncremental($tableName, $t['columns']);
                $log[] = "Created table: " . $tableName;
            } elseif ($t['status'] === 'outdated') {
                $db->add_columns($tableName, $t['missing_columns']);
                $log[] = "Updated table " . $tableName . " (added " . count($t['missing_columns']) . " columns)";
            }
        }

        // 2. Process Entities
        $entities = $this->Installation['entities'] ?? [];
        foreach ($entities as $entityClass) {
            if (class_exists($entityClass)) {
                call_user_func([$entityClass, 'install']);
                $log[] = "Synchronized entity: " . $entityClass;
            }
        }

        // 3. Process Sequences
        foreach ($deltas['sequences'] as $s) {
            \SPPMod\SPPDB\SPPSequence::createSequence($s['name'], $s['start'] ?? 1, $s['increment'] ?? 1);
            $log[] = "Created sequence: " . $s['name'];
        }

        // 4. Process Seeds
        $seeds = $this->Installation['seeds'] ?? [];
        foreach ($seeds as $table => $rows) {
            // Identify identity field (assume 'id' if not provided)
            $idField = 'id';
            if (isset($rows[0]) && is_array($rows[0])) {
                // If the first row has an 'id' field, use it.
                // Otherwise try to find any field that looks like a PK.
                if (!isset($rows[0]['id'])) {
                    $keys = array_keys($rows[0]);
                    $idField = $keys[0]; // Fallback to first field
                }
            }

            foreach ($rows as $row) {
                if ($db->safeInsert($table, $row, $idField)) {
                    $log[] = "Inserted seed record into $table ({$idField}=" . ($row[$idField] ?? '?') . ")";
                }
            }
        }

        return $log;
    }

    /**
     * static public function getSystemUpdateDeltas()
     * Scans all modules and independent app entities for installation deltas.
     * Centralized logic used by both API and CLI.
     */
    public static function getSystemUpdateDeltas(): array
    {
        self::loadAllModules();
        $summary = ['modules' => [], 'entities' => []];

        // 1. Collect all unique modules from global and current app context
        $allModules = [];

        // Global modules
        $globalModsReg = \SPP\Registry::$reg['__modobj'] ?? [];
        foreach ($globalModsReg as $name => $valkey) {
            $modVal = \SPP\Registry::$values[$valkey] ?? null;
            if ($modVal instanceof \SPP\Module) {
                $allModules[$name] = $modVal;
            }
        }

        // App-specific modules
        $context = \SPP\Scheduler::getContext();
        if ($context !== '' && isset(\SPP\Registry::$reg['__apps'][$context]['__modobj'])) {
            $appModsReg = \SPP\Registry::$reg['__apps'][$context]['__modobj'];
            foreach ($appModsReg as $name => $valkey) {
                $modVal = \SPP\Registry::$values[$valkey] ?? null;
                if ($modVal instanceof \SPP\Module) {
                    $allModules[$name] = $modVal;
                }
            }
        }

        foreach ($allModules as $name => $mod) {
            $deltas = $mod->getInstallationDeltas();
            // Check if any significant deltas exist
            if (!empty($deltas['tables']) || !empty($deltas['entities']) || !empty($deltas['sequences'])) {
                $summary['modules'][$name] = $deltas;
            }
        }

        // 2. Scan App Entities (YAML files in etc/apps/[context]/entities)
        $appname = $context ?: 'default';
        $entitiesDir = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'entities';
        if (is_dir($entitiesDir)) {
            $files = glob($entitiesDir . '/*.yml');
            $db = new \SPPMod\SPPDB\SPPDB();
            foreach ($files as $f) {
                $ename = basename($f, '.yml');

                // Refined Class Discovery
                $classCandidates = [
                    "\\SPPMod\\SPPEntity\\" . ucfirst($ename),
                    "\\SPPMod\\SPPEntity\\" . $ename,
                    ucfirst($ename),
                    $ename
                ];

                $className = null;
                foreach ($classCandidates as $candidate) {
                    if (class_exists($candidate) && is_subclass_of($candidate, "\\SPPMod\\SPPEntity\\SPPEntity")) {
                        $className = $candidate;
                        break;
                    }
                }

                $tname = "";
                if ($className) {
                    $tname = $className::getMetadata('table');
                } else {
                    // Fallback to basic pluralization/convention if class not found or matched
                    $tname = \SPPMod\SPPDB\SPPDB::sppTable(strtolower($ename) . 's');
                }

                if (!empty($tname) && !$db->tableExists((string) $tname)) {
                    $summary['entities'][] = ['name' => $ename, 'status' => 'missing', 'file' => basename($f)];
                }
            }
        }

        return $summary;
    }

    /**
     * static public function runSystemUpdate()
     * Runs the incremental installation routines for the entire system based on detected deltas.
     */
    public static function runSystemUpdate(): array
    {
        $summary = self::getSystemUpdateDeltas();
        $log = [];

        // 1. Run updates for modules
        foreach ($summary['modules'] as $name => $deltas) {
            $mod = null;
            // Resolve module again from registry for safety
            $modObj = \SPP\Registry::get('__modobj=>' . $name);
            if ($modObj instanceof \SPP\Module) {
                $mod = $modObj;
            } else {
                // Try app context
                $ctx = \SPP\Scheduler::getContext();
                if ($ctx) {
                    $modObj = \SPP\Registry::get('__apps=>' . $ctx . '=>__modobj=>' . $name);
                    if ($modObj instanceof \SPP\Module) {
                        $mod = $modObj;
                    }
                }
            }

            if ($mod) {
                try {
                    $modLog = $mod->runInstallation();
                    if (!empty($modLog)) {
                        $log = array_merge($log, array_map(fn ($l) => "Module [$name]: $l", $modLog));
                    } else {
                        $log[] = "Module [$name]: Already up to date.";
                    }
                } catch (\Exception $e) {
                    $log[] = "Module [$name] ERROR: " . $e->getMessage();
                }
            }
        }

        // 2. Run updates for app entities
        foreach ($summary['entities'] as $e) {
            $ename = $e['name'];
            $classCandidates = [
                "\\SPPMod\\SPPEntity\\" . ucfirst($ename),
                "\\SPPMod\\SPPEntity\\" . $ename,
                ucfirst($ename),
                $ename
            ];

            $className = null;
            foreach ($classCandidates as $candidate) {
                if (class_exists($candidate) && is_subclass_of($candidate, "\\SPPMod\\SPPEntity\\SPPEntity")) {
                    $className = $candidate;
                    break;
                }
            }

            if ($className) {
                try {
                    call_user_func([$className, 'install']);
                    $log[] = "Entity [$ename]: Schema synchronized successfully using class $className.";
                } catch (\Exception $ex) {
                    $log[] = "Entity [$ename] ERROR: " . $ex->getMessage();
                }
            } else {
                $log[] = "Entity [$ename]: ERROR - Entity class not found for automated installation.";
            }
        }

        return $log;
    }

    /**
     * Resolves the appropriate modsconf directory for a module.
     * System modules are restricted to framework-internal paths.
     */

    /**
     * Saves multiple config variables for a module within a specific app context.
     * Writes to the file only once.
     *
     * @param array  $variables Key-value pairs to save
     * @param string $modname   Module internal name
     * @param string $appname   Application name
     */
    public static function setAllConfigForApp(array $variables, string $modname, string $appname): void
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $appname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname);

        $modsConfDir = self::getEffectiveModsConfDir($modname, $appname);
        $yamlConfFile = $modsConfDir . SPP_DS . $modname . SPP_DS . 'config.yml';

        $yamlData = [];
        if (file_exists($yamlConfFile)) {
            $yamlData = Yaml::parseFile($yamlConfFile) ?? [];
        } else {
            $dir = dirname($yamlConfFile);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new \SPP\SPPException("Failed to create config directory: {$dir}");
                }
            }
        }

        if (!isset($yamlData['variables']) || !is_array($yamlData['variables'])) {
            $legacyValues = $yamlData;
            $yamlData = ['variables' => []];
            foreach ($legacyValues as $key => $existingValue) {
                if ($key !== 'variables' && is_string($key)) {
                    $yamlData['variables'][$key] = $existingValue;
                }
            }
        }

        foreach ($variables as $varname => $value) {
            $varname = str_replace(["'", '"'], '', $varname);
            $yamlData['variables'][$varname] = $value;

            // Invalidate cache for each variable
            $context = $appname ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $appname) : \SPP\Scheduler::getContext();
            $cacheKey = $context . '::' . $modname . '::' . $varname;
            self::$configCache[$cacheKey] = (string) $value;
        }

        file_put_contents($yamlConfFile, Yaml::dump($yamlData, 4, 4));
        $logFile = (defined('SPP_BASE_DIR') ? SPP_BASE_DIR : __DIR__) . '/../var/logs/api_save.log';
        error_log("[" . date('Y-m-d H:i:s') . "] MODULE DEBUG: setAllConfigForApp saved to $yamlConfFile. Vars: " . count($variables) . "\n", 3, $logFile);
    }

    /**
     * Resolves the appropriate modsconf directory for a module based on context and type.
     * This is the CENTRAL AUTHORITY for all module configuration paths.
     *
     * @param string $modname Module internal name
     * @param string $appname Application name
     * @return string Absolute path to the directory containing the module's subfolder
     */
    public static function getEffectiveModsConfDir(string $modname, string $appname): string
    {
        $appname = $appname ?: \SPP\Scheduler::getContext();
        $app = \SPP\App::getApp($appname);

        // 1. Primary: The application's own etc/modsconf (Contained within app)
        $appModsConf = $app->getModsConfDir();
        if (is_dir($appModsConf . SPP_DS . $modname)) {
            return $appModsConf;
        }

        // 2. Secondary: Global system path for framework-internal apps (Fallback)
        // Path: spp/etc/apps/<appname>/modsconf/
        $systemAppModsConf = SPP_ETC_DIR . SPP_DS . 'apps' . SPP_DS . $appname . SPP_DS . 'modsconf';
        if (is_dir($systemAppModsConf . SPP_DS . $modname)) {
            return $systemAppModsConf;
        }

        // 3. Fallback: Default to the application's config directory
        // Even if the module subfolder doesn't exist yet, we prefer the app's etc/modsconf for new configs.
        return $appModsConf;
    }

    /**
     * Returns an array of potential module registry files (modules.yml/xml) for an app.
     * Centralizes lookup priority for all module manifests.
     *
     * @param string $appname
     * @return array<array{file: string, type: string}>
     */
    public static function getRegistryFiles(string $appname): array
    {
        $registries = [];
        if ($appname === '') {
            return $registries;
        }

        $app = \SPP\App::getApp($appname);
        $candidates = [
            // 1. App-Specific (Owned by the app)
            ['path' => $app->getModsConfDir() . SPP_DS . 'modules', 'type' => 'user'],
            // 2. Framework Override (Owned by framework, for this app)
            ['path' => SPP_ETC_DIR . SPP_DS . 'apps' . SPP_DS . $appname . SPP_DS . 'modules', 'type' => 'system'],
            // 3. Global Framework Defaults
            ['path' => SPP_ETC_DIR . SPP_DS . 'modules', 'type' => 'system']
        ];

        foreach ($candidates as $c) {
            if (file_exists($c['path'] . '.yml')) {
                $registries[] = ['file' => $c['path'] . '.yml', 'type' => $c['type']];
            } elseif (file_exists($c['path'] . '.xml')) {
                $registries[] = ['file' => $c['path'] . '.xml', 'type' => $c['type']];
            }
        }

        return $registries;
    }

    /**
     * Returns an array of directories that may contain loosely coupled user modules for an app.
     *
     * @param string $appname
     * @return array<string>
     */
    public static function getAppModuleDirs(string $appname): array
    {
        if ($appname === '' || $appname === 'default') {
            return [];
        }
        $app = \SPP\App::getApp($appname);

        $dirs = [];
        $dirs[] = $app->getAppSrcDir() . SPP_DS . 'modules';

        return $dirs;
    }

    /**
     * Returns a list of all available modules (system and user) for a context.
     *
     * @param string $appname
     * @return array<array>
     */
    public static function listAvailableModules(string $appname): array
    {
        $manifests = [];

        // 1. Discover System Modules
        $sysManifests = array_merge(
            \SPP\SPPFS::findFile('module.yml', SPP_MODULES_DIR) ?: [],
            \SPP\SPPFS::findFile('module.xml', SPP_MODULES_DIR) ?: []
        );
        foreach ($sysManifests as $f) {
            $name = basename(dirname($f));
            if (!isset($manifests[$name])) {
                $manifests[$name] = ['file' => $f, 'type' => 'system'];
            }
        }

        // 2. Discover User/App Modules
        foreach (self::getAppModuleDirs($appname) as $dir) {
            $userManifests = array_merge(
                \SPP\SPPFS::findFile('module.yml', $dir) ?: [],
                \SPP\SPPFS::findFile('module.xml', $dir) ?: []
            );
            foreach ($userManifests as $f) {
                $name = basename(dirname($f));
                $manifests[$name] = ['file' => $f, 'type' => 'user'];
            }
        }

        $modules = [];
        foreach ($manifests as $name => $info) {
            try {
                $mod = new \SPP\Module($info['file']);
                $status = self::getModuleStatus($name, $appname);

                $category = $mod->ModuleCategory ?: '';
                if ($category === '') {
                    $category = ($info['type'] === 'system') ? 'Core Optional' : 'App Modules';
                }

                $modules[] = [
                    'name' => $mod->InternalName ?: $name,
                    'public_name' => $mod->PublicName ?: ($mod->InternalName ?: $name),
                    'version' => $mod->Version ?: '1.0',
                    'description' => $mod->PublicDesc ?: '',
                    'author' => $mod->Author ?? 'Unknown',
                    'active' => $status === 'active',
                    'type' => $info['type'],
                    'path' => $mod->ModPath,
                    'dependencies' => (array) ($mod->Dependencies ?? []),
                    'module_group' => $mod->ModuleGroup ?: 'General',
                    'module_category' => $category,
                    'has_config' => (!empty($mod->ConfigVariables) || !empty($mod->Settings)),
                    'manifest' => $info['file']
                ];
            } catch (\Exception $e) {
            }
        }

        return $modules;
    }

    /**
     * Returns the status of a module in a specific app context.
     *
     * @param string $modname
     * @param string $appname
     * @return string 'active' | 'inactive'
     */
    public static function getModuleStatus(string $modname, string $appname): string
    {
        $registries = self::getRegistryFiles($appname);
        foreach ($registries as $r) {
            $file = $r['file'];
            if (!file_exists($file)) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'yml' || $ext === 'yaml') {
                $data = \Symfony\Component\Yaml\Yaml::parseFile($file);
                $mods = $data['modules'] ?? [];
                foreach ($mods as $m) {
                    $mArr = (array) $m;
                    if (($mArr['name'] ?? $mArr['modname'] ?? '') === $modname) {
                        return (string) ($mArr['status'] ?? 'active');
                    }
                }
            } else {
                $xml = @simplexml_load_file($file);
                if ($xml === false) {
                    continue;
                }
                foreach ($xml->module as $mod) {
                    $name = (string) ($mod->modname ?? $mod->name ?? '');
                    if ($name === $modname) {
                        return (string) ($mod->status ?? 'active');
                    }
                }
            }
        }

        return 'inactive';
    }
}
