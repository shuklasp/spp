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
        'Author',
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
        'ServiceProvider',
        'Type',
        'Compulsory'
    ];

    /** @var array<string> */
    protected array $_getprops = [
        'PublicName',
        'PublicDesc',
        'InternalName',
        'Author',
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
        'ServiceProvider',
        'Type',
        'Compulsory'
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
     * Module author
     * @var string
     */
    public string $Author = '';

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
    private static array $_system_module_roots = ['', 'spp', 'optional', 'contrib', 'school', 'custom'];

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
    public static function injectManifestCache(string $file, array $data): void
    {
        self::$moduleManifestCache[$file] = $data;
    }

    public static function getManifestCache(string $file): ?array
    {
        return self::$moduleManifestCache[$file] ?? null;
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

    public static function safeParseYaml(string $file): array
    {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        return (array)Yaml::parse($raw);
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
        $parsed = self::safeParseYaml($file);

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
                case 'id':
                    $this->InternalName = (string) $val;
                    break;
                case 'name':
                    if (empty($this->InternalName)) {
                        $this->InternalName = (string) $val;
                    }
                    if (empty($this->PublicName)) {
                        $this->PublicName = (string) $val;
                    }
                    break;
                case 'author':
                    $this->Author = (string) $val;
                    break;
                case 'version':
                    $this->Version = (string) $val;
                    break;
                case 'type':
                    $this->Type = (string) $val;
                    break;
                case 'compulsory':
                    $this->Compulsory = filter_var($val, FILTER_VALIDATE_BOOLEAN);
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
                case 'desc':
                case 'description':
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
                case 'provider':
                case 'service_provider':
                case 'serviceprovider':
                    $this->ServiceProvider = (string) $val;
                    break;
                case 'type':
                    $this->_attributes['type'] = (string) $val;
                    break;
                case 'compulsory':
                    $this->_attributes['compulsory'] = (bool) $val;
                    break;
                default:
                    // Ignore unknown keys (keep robust)
                    break;
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
            $data = self::safeParseYaml($path);
            return $data[$root][$key] ?? $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Return dependencies as a flat, indexed array of strings.
     * Handles string lists, YAML dictionaries, Composer version maps, and nested arrays.
     *
     * @return array<string>
     */
    public function getNormalizedDependencies(): array
    {
        $deps = $this->Dependencies ?? [];
        if (is_string($deps)) {
            $parts = explode(',', $deps);
            $normalized = [];
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '') $normalized[] = $p;
            }
            return array_values(array_unique($normalized));
        }
        if (!is_array($deps)) {
            return [];
        }
        $normalized = [];
        foreach ($deps as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $subVal) {
                    if (is_string($subVal) || is_numeric($subVal)) {
                        $normalized[] = (string) $subVal;
                    }
                }
            } elseif (is_string($val) || is_numeric($val)) {
                if (is_string($key) && !is_numeric($key)) {
                    $normalized[] = "{$key} ({$val})";
                } else {
                    $normalized[] = (string) $val;
                }
            } elseif (is_string($key) && !is_numeric($key)) {
                $normalized[] = $key;
            }
        }
        return array_values(array_unique($normalized));
    }

    /**
     * Return dependency module names only (stripping version numbers or decorators).
     *
     * @return array<string>
     */
    public function getDependencyNames(): array
    {
        $names = [];
        foreach ($this->getNormalizedDependencies() as $dep) {
            $name = trim(explode('(', $dep)[0]);
            $name = trim(explode(' ', $name)[0]);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * Returns true if the module is marked as compulsory in global-settings.yml.
     *
     * @param string $modname
     * @return bool
     */
    public static function isCompulsory(string $modname): bool
    {
        // 1. Check legacy global-settings.yml
        $compulsory = self::getGlobalConfig('settings', 'compulsory_modules', []);
        if (in_array($modname, $compulsory)) {
            return true;
        }

        // 2. Check modernized module.yml type and compulsory flag
        try {
            $mod = \SPP\Registry::get('__modobj=>' . $modname);
            if (!$mod) {
                $modPath = \SPP\Registry::get('__mods=>' . $modname);
                if ($modPath && file_exists($modPath . '/module.yml')) {
                    $mod = new self($modPath . '/module.yml');
                }
            }
            if ($mod && $mod->Type === 'core' && $mod->Compulsory === true) {
                return true;
            }
        } catch (\Exception $e) {
            // Ignore if module not found
        }

        return false;
    }

    /**
     * Gets a config variable for the module.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $varname
     * @param string $modname
     * @param string|null $appname Optional app context
     * @return mixed
     * @throws \SPP\SPPException
     */
    public static function getConfig(string $varname, string $modname, ?string $appname = null, mixed $default = false): mixed
    {
        return \SPP\Core\ModuleConfigManager::getConfig($varname, $modname, $appname, $default);
    }

    /**
     * Retrieves the entire configuration array for a module in a specific app.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname
     * @param string|null $appname
     * @return array
     */
    public static function getAppConfig(string $modname, ?string $appname = null): array
    {
        return \SPP\Core\ModuleConfigManager::getAppConfig($modname, $appname);
    }

    /**
     * Persists a bulk configuration array for a module.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname
     * @param string $appname
     * @param array $config
     * @return bool
     */
    public static function saveAppConfig(string $modname, string $appname, array $config): bool
    {
        return \SPP\Core\ModuleConfigManager::saveAppConfig($modname, $appname, $config);
    }

    /**
     * Sets a config variable for the module.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $varname Config variable name
     * @param mixed  $value   Value to store (will be cast to string in YAML)
     * @param string $modname Module internal name
     * @param string|null $appname
     * @return void
     * @throws \SPP\SPPException
     */
    public static function setConfig(string $varname, mixed $value, string $modname, ?string $appname = null): void
    {
        \SPP\Core\ModuleConfigManager::setConfig($varname, $value, $modname, $appname);
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
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname
     * @param string|null $appname
     * @return string
     */
    public static function getExpectedConfigPath(string $modname, ?string $appname = null): string
    {
        return \SPP\Core\ModuleConfigManager::getExpectedConfigPath($modname, $appname);
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
        if ($modpath === false || empty($modpath)) {
            $modpath = self::getModuleDir($modname);
            if (!$modpath) {
                throw new \SPP\SPPException("Module not registered: {$modname}");
            }
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
        $manifests = [];
        foreach (self::listAvailableModules(\SPP\Scheduler::getContext()) as $info) {
            $manifests[] = $info['file'];
        }
        return $manifests;
    }

    public function getPath(): string
    {
        return $this->ModPath;
    }

    /**
     * Resolves the root directory path of a module.
     *
     * @param string $modname Internal name of the module
     * @return string|null
     */
    public static function getModuleDir(string $modname): ?string
    {
        $modname = preg_replace('/[^a-zA-Z0-9_\-]/', '', $modname);
        $path = \SPP\Registry::get('__mods=>' . $modname);
        if ($path && is_string($path) && is_dir($path)) {
            return $path;
        }

        $manifest = self::findManifestPath($modname, 'system');
        if (!$manifest) {
            $manifest = self::findManifestPath($modname, 'user');
        }

        return $manifest ? dirname($manifest) : null;
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
            $app = \SPP\App::getInstance($appname);
            if ($app !== null) {
                $userDir = $app->getModDir() . SPP_DS . $modname;
            } else {
                $modPath = \SPP\App::getAppConf('modules_path', $appname) ?: 'modules';
                $srcPath = \SPP\App::getAppConf('src_path', $appname);
                $base = ($srcPath !== null && $srcPath !== '')
                    ? (SPP_APP_DIR . SPP_DS . ltrim(str_replace('\\', '/', $srcPath), '/'))
                    : (SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $appname);
                $userDir = $base . SPP_DS . trim(str_replace('\\', '/', $modPath), '/') . SPP_DS . $modname;
            }
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
    public static function loadAllModules(bool $forceReload = false): void
    {
        $appname = \SPP\Scheduler::getContext();

        // Track loaded modules per app context to avoid redundant work
        // but ensure services are registered for every app instance.
        static $loadedContexts = [];
        
        if ($forceReload) {
            unset($loadedContexts[$appname]);
            \SPP\Registry::register('__mods', null);
            \SPP\Registry::register('__modobj', null);
        }
        
        if (isset($loadedContexts[$appname])) {
            return;
        }
        $loadedContexts[$appname] = true;

        // --- Phase 1: Try Compiled Cache (High Performance) ---
        $cacheFile = \SPP\Core\ModuleCompiler::getCachePath($appname);
        if (file_exists($cacheFile)) {
            $compiled = require $cacheFile;
            if (is_array($compiled)) {
                $meta = $compiled['__meta'] ?? ['manifest_mtime' => 0];
                unset($compiled['__meta']);

                // Verify cache validity by checking timestamps and environment root
                $isValid = true;
                $currentAppDir = defined('SPP_APP_DIR') ? str_replace('\\', '/', SPP_APP_DIR) : '';
                $cachedAppDir = isset($meta['app_dir']) ? str_replace('\\', '/', (string)$meta['app_dir']) : null;
                if ($cachedAppDir !== null && $currentAppDir !== '' && $cachedAppDir !== $currentAppDir) {
                    $isValid = false;
                }

                $registries = self::getRegistryFiles($appname);
                $manifests = [];
                foreach ($registries as $r) {
                    $manifests[] = $r['file'];
                }
                foreach ($manifests as $m) {
                    if (@file_exists($m) && @filemtime($m) > $meta['manifest_mtime']) {
                        $isValid = false; break;
                    }
                }

                if ($isValid) {
                    foreach ($compiled as $name => &$data) {
                        $data['path'] = self::resolveCachedModPath($data['path']);
                        $modManifest = $data['path'] . SPP_DS . 'module.yml';
                        if (!@file_exists($modManifest)) $modManifest = $data['path'] . SPP_DS . 'module.xml';
                        if (@file_exists($modManifest) && @filemtime($modManifest) > $meta['manifest_mtime']) {
                            $isValid = false; break;
                        }
                    }
                    unset($data);
                }

                if ($isValid) {
                    foreach ($compiled as $name => $data) {
                        self::initFromCache($name, $data);
                    }
                    self::$allModulesLoaded = true;
                    return;
                }
            }
        }

        // --- Phase 2: Runtime Topological Boot ---
        // If not using the compiled cache, we rely on the compiler dynamically
        // to discover and topologically sort modules to ensure dependencies are honored.
        $compiler = new \SPP\Core\ModuleCompiler($appname);
        try {
            $registry = $compiler->compileToArray();
            // Automatically persist compiled cache so subsequent requests are instant
            $compiler->writeCache($registry);

            foreach ($registry as $name => $data) {
                if ($name === '__meta') continue;
                self::initFromCache($name, $data);
            }
        } catch (\SPP\Exceptions\MissingDependencyException $e) {
            throw new \SPP\SPPException("Module Boot Error: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \SPP\SPPException("Failed to boot modules: " . $e->getMessage());
        }

        self::$allModulesLoaded = true;
    }

    private static function resolveCachedModPath(string $path): string
    {
        $appDir = defined('SPP_APP_DIR') ? str_replace('\\', '/', SPP_APP_DIR) : '';
        $baseDir = defined('SPP_BASE_DIR') ? str_replace('\\', '/', SPP_BASE_DIR) : '';
        $norm = str_replace('\\', '/', $path);

        // Portable relative path (e.g. "./spp/modules/...")
        if (str_starts_with($norm, './')) {
            $resolved = SPP_APP_DIR . SPP_DS . str_replace('/', SPP_DS, substr($norm, 2));
            if (@file_exists($resolved)) {
                return $resolved;
            }
        }

        // If path belongs to current app dir or base dir, verify directly
        if (($appDir !== '' && str_starts_with($norm, $appDir)) || ($baseDir !== '' && str_starts_with($norm, $baseDir))) {
            if (@file_exists($path)) {
                return $path;
            }
        }

        // Handle re-basing for relocated workspaces across platforms / servers
        if (str_contains($norm, '/spp/modules/')) {
            $sub = substr($norm, strpos($norm, '/spp/modules/') + strlen('/spp/modules/'));
            $alt = (defined('SPP_MODULES_DIR') ? SPP_MODULES_DIR : (SPP_BASE_DIR . '/modules')) . SPP_DS . str_replace('/', SPP_DS, $sub);
            if (@file_exists($alt)) {
                return $alt;
            }
        } elseif (str_contains($norm, '/modules/')) {
            $sub = substr($norm, strpos($norm, '/modules/') + strlen('/modules/'));
            $alt = SPP_APP_DIR . SPP_DS . 'modules' . SPP_DS . str_replace('/', SPP_DS, $sub);
            if (@file_exists($alt)) {
                return $alt;
            }
        }

        if (@file_exists($path)) {
            return $path;
        }

        return $path;
    }

    /**
     * Rapid initialization of a module from compiled cache data.
     */
    private static function initFromCache(string $name, array $data): void
    {
        $data['path'] = self::resolveCachedModPath($data['path']);
        \SPP\Registry::register('__mods=>' . $name, $data['path']);

        $manifestPath = $data['path'] . SPP_DS . 'module.yml';
        if (!file_exists($manifestPath)) {
            $manifestPath = $data['path'] . SPP_DS . 'module.xml';
        }

        if (isset($data['raw_manifest'])) {
            self::injectManifestCache($manifestPath, $data['raw_manifest']);
        }

        $module = new self($manifestPath);
        $module->ModuleType = $data['type'];

        \SPP\Registry::register('__modobj=>' . $name, $module);
        
        if (!empty($data['namespace'])) {
            $nsMap = \SPP\Registry::getArray('__modns');
            $nsMap[$data['namespace']] = $data['path'];
            \SPP\Registry::register('__modns', $nsMap);
        }

        if (!empty($data['services'])) {
            $module->registerServices($data['services']);
        }

        if (!empty($data['routes'])) {
            $module->Routes = (array) $data['routes'];
        }

        foreach ($data['includes'] as $file) {
            require_once $data['path'] . SPP_DS . $file;
        }

        if (!empty($data['has_modinit'])) {
            require_once $data['path'] . SPP_DS . 'modinit.php';
        }

        // Boot Service Provider if declared
        $providerClass = $data['provider'] ?? ($module->ServiceProvider ?? null);
        if ($providerClass) {
            if (!class_exists($providerClass)) {
                $basename = substr(strrchr($providerClass, "\\"), 1) ?: $providerClass;
                $possibleFile = $data['path'] . SPP_DS . 'src' . SPP_DS . $basename . '.php';
                if (file_exists($possibleFile)) {
                    require_once $possibleFile;
                }
            }
            if (class_exists($providerClass)) {
                $provider = new $providerClass();
                if ($provider instanceof \SPP\Core\Interfaces\ServiceProviderInterface) {
                    $provider->register();
                    $provider->boot();
                }
            }
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
                    $parsed = self::safeParseYaml($file);
                    $mods = $parsed['modules'] ?? [];
                    self::$manifestFileCache[$file] = $mods;
                }
            }
        } catch (\Exception $e) {
            return;
        }

        $deadModules = [];

        foreach ($mods as $mod) {
            if (!$mod) {
                continue;
            }
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
                : \SPP\App::getApp($appname)->getModDir();

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
            $data = self::safeParseYaml($file);
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

            $data = file_exists($preferred) ? self::safeParseYaml($preferred) : ['modules' => []];
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
            $userBase = \SPP\App::getApp($appname)->getModDir();
            $base = str_replace('\\', '/', realpath($userBase) ?: $userBase);
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
        return \SPP\Core\ModuleConfigManager::ensureConfigForApp($modname, $appname);
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
        $parsed = self::safeParseYaml($file);
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
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname Module internal name
     * @return array ['variables' => [...], 'source' => string]
     */
    public static function getAllConfig(string $modname): array
    {
        return \SPP\Core\ModuleConfigManager::getAllConfig($modname);
    }

    /**
     * Returns the raw content of a module's config file for direct editing.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname Module internal name
     * @return array ['content' => string, 'path' => string, 'format' => string]
     */
    public static function getRawConfig(string $modname): array
    {
        return \SPP\Core\ModuleConfigManager::getRawConfig($modname);
    }

    /**
     * Returns all config variables for a module within a specific app context.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname Module internal name
     * @param string $appname Application name
     * @return array ['variables' => [...], 'source' => string]
     */
    public static function getAllConfigForApp(string $modname, string $appname): array
    {
        return \SPP\Core\ModuleConfigManager::getAllConfigForApp($modname, $appname);
    }

    /**
     * Returns raw config file content for a module within a specific app context.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname Module internal name
     * @param string $appname Application name
     * @return array ['content' => string, 'path' => string, 'format' => string]
     */
    public static function getRawConfigForApp(string $modname, string $appname): array
    {
        return \SPP\Core\ModuleConfigManager::getRawConfigForApp($modname, $appname);
    }

    /**
     * Saves a config variable for a module within a specific app context.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $varname Variable name
     * @param mixed  $value   Value
     * @param string $modname Module internal name
     * @param string $appname Application name
     * @return void
     */
    public static function setConfigForApp(string $varname, mixed $value, string $modname, string $appname): void
    {
        \SPP\Core\ModuleConfigManager::setConfigForApp($varname, $value, $modname, $appname);
    }

    /**
     * Scans the module's installation requirements and returns a list of deltas
     * compared to the current database state.
     *
     * @return array
     */
    public function getInstallationDeltas(): array
    {
        $db = \SPP\DB::getInstance();
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
            $tnameFull = \SPP\DB::sppTable($tname);
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
                    if ($reflection->isSubclassOf('\\SPP\\Core\\EntityInterface')) {
                        // Check if the table for this entity exists
                        $entityName = $reflection->getShortName();
                        $tableName = $entityClass::getMetadata('table');
                        if (!$tableName) {
                            $tableName = strtolower($entityName) . 's';
                        }
                        $tableName = \SPP\DB::sppTable($tableName);

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

            $start = is_array($sDef) ? ($sDef['start'] ?? 1) : $sDef;
            $inc = is_array($sDef) ? ($sDef['increment'] ?? 1) : 1;

            $deltas['sequences'][] = [
                'name' => $seqName,
                'status' => 'missing',
                'start' => (int) $start,
                'increment' => (int) $inc
            ];
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
        $db = \SPP\DB::getInstance();
        $deltas = $this->getInstallationDeltas();
        $log = [];

        // 1. Process Tables
        foreach ($deltas['tables'] as $t) {
            $tableName = \SPP\DB::sppTable($t['name']);
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
            \SPP\DB\Sequence::createSequence($s['name'], $s['start'] ?? 1, $s['increment'] ?? 1);
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
            $db = \SPP\DB::getInstance();
            foreach ($files as $f) {
                $ename = basename($f, '.yml');

                // Refined Class Discovery
                $classCandidates = [
                    "\\App\\" . ucfirst($appname) . "\\Entities\\" . ucfirst($ename),
                    ucfirst($ename),
                    $ename
                ];

                $className = null;
                foreach ($classCandidates as $candidate) {
                    if (class_exists($candidate) && is_subclass_of($candidate, "\\SPP\\Core\\EntityInterface")) {
                        $className = $candidate;
                        break;
                    }
                }

                $tname = "";
                if ($className) {
                    $tname = $className::getMetadata('table');
                } else {
                    // Fallback to basic pluralization/convention if class not found or matched
                    $tname = \SPP\DB::sppTable(strtolower($ename) . 's');
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
                "\\App\\" . ucfirst(\SPP\Scheduler::getContext()) . "\\Entities\\" . ucfirst($ename),
                ucfirst($ename),
                $ename
            ];

            $className = null;
            foreach ($classCandidates as $candidate) {
                if (class_exists($candidate) && is_subclass_of($candidate, "\\SPP\\Core\\EntityInterface")) {
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
        \SPP\Core\ModuleConfigManager::setAllConfigForApp($variables, $modname, $appname);
    }

    /**
     * Resolves the appropriate modsconf directory for a module based on context and type.
     * Delegates to \SPP\Core\ModuleConfigManager.
     *
     * @param string $modname Module internal name
     * @param string $appname Application name
     * @return string Absolute path to the directory containing the module's subfolder
     */
    public static function getEffectiveModsConfDir(string $modname, string $appname): string
    {
        return \SPP\Core\ModuleConfigManager::getEffectiveModsConfDir($modname, $appname);
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

        $modsConfDir = self::resolveAppModsConfDir($appname);
        $candidates = [
            // 1. App-Specific (Owned by the app)
            ['path' => $modsConfDir . SPP_DS . 'modules', 'type' => 'user'],
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

    public static function resolveAppModsConfDir(string $appname): string
    {
        $app = \SPP\App::getInstance($appname);
        if ($app !== null) {
            return $app->getModsConfDir();
        }

        $modsConfPath = \SPP\App::getAppConf('modsconf_path', $appname);
        if ($modsConfPath !== null && $modsConfPath !== '') {
            return str_starts_with($modsConfPath, '/') || str_contains($modsConfPath, ':')
                ? $modsConfPath
                : SPP_APP_DIR . SPP_DS . ltrim(str_replace('\\', '/', $modsConfPath), '/');
        }

        $etcPath = \SPP\App::getAppConf('etc_path', $appname);
        if ($etcPath !== null && $etcPath !== '') {
            $base = str_starts_with($etcPath, '/') || str_contains($etcPath, ':')
                ? $etcPath
                : SPP_APP_DIR . SPP_DS . ltrim(str_replace('\\', '/', $etcPath), '/');
            return rtrim($base, '/\\') . SPP_DS . 'modsconf';
        }

        $srcPath = \SPP\App::getAppConf('src_path', $appname);
        if ($srcPath !== null && $srcPath !== '') {
            $base = str_starts_with($srcPath, '/') || str_contains($srcPath, ':')
                ? $srcPath
                : SPP_APP_DIR . SPP_DS . ltrim(str_replace('\\', '/', $srcPath), '/');
            $candidate = rtrim($base, '/\\') . SPP_DS . 'etc' . SPP_DS . 'modsconf';
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        $candidates = [
            APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'modsconf',
            SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $appname . SPP_DS . 'etc' . SPP_DS . 'modsconf',
            SPP_ETC_DIR . SPP_DS . 'apps' . SPP_DS . $appname . SPP_DS . 'modsconf'
        ];
        foreach ($candidates as $c) {
            if (is_dir($c)) {
                return $c;
            }
        }

        return APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'modsconf';
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
        $app = \SPP\App::getInstance($appname);
        if ($app !== null) {
            return [$app->getAppSrcDir() . SPP_DS . 'modules'];
        }

        $srcPath = \SPP\App::getAppConf('src_path', $appname);
        $srcDir = ($srcPath !== null && $srcPath !== '')
            ? (SPP_APP_DIR . SPP_DS . ltrim(str_replace('\\', '/', $srcPath), '/'))
            : (SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $appname);

        return [$srcDir . SPP_DS . 'modules'];
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

        // 1. Discover System Modules via targeted scope scan (avoids slow deep recursion)
        $scopes = ['spp', 'optional', 'contrib'];
        foreach ($scopes as $scope) {
            $scopeDir = SPP_MODULES_DIR . SPP_DS . $scope;
            if (!is_dir($scopeDir)) continue;
            $mods = @scandir($scopeDir) ?: [];
            foreach ($mods as $m) {
                if ($m === '.' || $m === '..') continue;
                $modDir = $scopeDir . SPP_DS . $m;
                if (!is_dir($modDir)) continue;
                if (file_exists($modDir . SPP_DS . 'module.yml')) {
                    $manifests[$m] = ['file' => $modDir . SPP_DS . 'module.yml', 'type' => 'system'];
                } elseif (file_exists($modDir . SPP_DS . 'module.xml')) {
                    $manifests[$m] = ['file' => $modDir . SPP_DS . 'module.xml', 'type' => 'system'];
                }
            }
        }
        // Direct root scan for non-scoped system modules
        $mods = @scandir(SPP_MODULES_DIR) ?: [];
        foreach ($mods as $m) {
            if ($m === '.' || $m === '..' || in_array($m, $scopes, true)) continue;
            $modDir = SPP_MODULES_DIR . SPP_DS . $m;
            if (!is_dir($modDir)) continue;
            if (file_exists($modDir . SPP_DS . 'module.yml')) {
                $manifests[$m] = ['file' => $modDir . SPP_DS . 'module.yml', 'type' => 'system'];
            } elseif (file_exists($modDir . SPP_DS . 'module.xml')) {
                $manifests[$m] = ['file' => $modDir . SPP_DS . 'module.xml', 'type' => 'system'];
            }
        }

        // 2. Discover User/App Modules via targeted app directory scan
        foreach (self::getAppModuleDirs($appname) as $dir) {
            if (!is_dir($dir)) continue;
            $mods = @scandir($dir) ?: [];
            foreach ($mods as $m) {
                if ($m === '.' || $m === '..') continue;
                $modDir = $dir . SPP_DS . $m;
                if (!is_dir($modDir)) continue;
                if (file_exists($modDir . SPP_DS . 'module.yml')) {
                    $manifests[$m] = ['file' => $modDir . SPP_DS . 'module.yml', 'type' => 'user'];
                } elseif (file_exists($modDir . SPP_DS . 'module.xml')) {
                    $manifests[$m] = ['file' => $modDir . SPP_DS . 'module.xml', 'type' => 'user'];
                }
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
                    'dependencies' => $mod->getNormalizedDependencies(),
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
                $data = self::safeParseYaml($file);
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
