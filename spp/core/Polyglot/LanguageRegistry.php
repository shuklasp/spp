<?php
declare(strict_types=1);

namespace SPP\Core\Polyglot;

use Symfony\Component\Yaml\Yaml;

/**
 * Class LanguageRegistry
 *
 * Single authoritative registry for all polyglot language drivers in the SPP framework.
 * Dynamically loads, validates, and indexes language definitions from declarative YAML manifests.
 * Provides manifest schema validation, runtime binary discovery, cache versioning & freshness validation.
 */
class LanguageRegistry
{
    public const CACHE_VERSION = '2.1';

    private static array $drivers = [];
    private static array $extensionMap = [];
    private static bool $booted = false;
    private static ?array $disabledCache = null;
    private static array $validationErrors = [];
    private static ?array $cachedRuntimes = null;

    /**
     * Resolves the cache file path.
     */
    public static function getCacheFilePath(): string
    {
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4));
        return $appDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'polyglot_runtimes.json';
    }

    /**
     * Initializes and loads all language manifests from framework and application paths.
     */
    public static function boot(bool $force = false): void
    {
        if (self::$booted && !$force) {
            return;
        }

        self::$drivers = [];
        self::$extensionMap = [];
        self::$validationErrors = [];

        $searchDirs = [
            SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'polyglot',
            SPP_APP_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'polyglot',
        ];

        // Scan application-specific polyglot directories if Scheduler context is active
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(SPP_BASE_DIR);
        $appsDir = $appDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'apps';
        if (is_dir($appsDir)) {
            $appPolyglots = glob($appsDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'polyglot', GLOB_ONLYDIR);
            if ($appPolyglots) {
                foreach ($appPolyglots as $dir) {
                    $searchDirs[] = $dir;
                }
            }
        }

        foreach (array_unique($searchDirs) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = glob($dir . DIRECTORY_SEPARATOR . '*.yml') ?: [];
            foreach ($files as $file) {
                if (basename($file) === 'status.yml') {
                    continue;
                }

                try {
                    $parsed = Yaml::parseFile($file);
                    if (isset($parsed['language']) && is_array($parsed['language'])) {
                        $manifest = $parsed['language'];
                        $errors = self::validateManifest($manifest, $file);
                        if (!empty($errors)) {
                            self::$validationErrors[$file] = $errors;
                        }
                        self::register($manifest);
                    }
                } catch (\Throwable $e) {
                    self::$validationErrors[$file] = ["YAML syntax error: " . $e->getMessage()];
                    error_log("LanguageRegistry: Failed to parse manifest {$file}: " . $e->getMessage());
                }
            }
        }

        self::$booted = true;
    }

    /**
     * Validates a language manifest schema.
     *
     * @param array $manifest
     * @param string $sourceFile
     * @return array<string> List of validation error messages
     */
    public static function validateManifest(array $manifest, string $sourceFile = ''): array
    {
        $errors = [];

        if (empty($manifest['id']) || !is_string($manifest['id'])) {
            $errors[] = "Missing or invalid 'id' string in manifest.";
        }

        if (empty($manifest['name']) || !is_string($manifest['name'])) {
            $errors[] = "Missing or invalid 'name' string in manifest.";
        }

        if (!isset($manifest['extensions']) || !is_array($manifest['extensions']) || empty($manifest['extensions'])) {
            $errors[] = "Missing or empty 'extensions' array in manifest.";
        }

        $validCategories = ['interpreted', 'compiled', 'dsl', 'virtual'];
        $category = $manifest['category'] ?? 'interpreted';
        if (!in_array($category, $validCategories, true)) {
            $errors[] = "Invalid category '{$category}'. Must be one of: " . implode(', ', $validCategories);
        }

        if ($category === 'dsl' && empty($manifest['dsl_engine'])) {
            $errors[] = "Category 'dsl' requires 'dsl_engine' class specification.";
        }

        if ($category === 'compiled' && empty($manifest['execution']['compilation'])) {
            $errors[] = "Category 'compiled' requires 'execution.compilation' block.";
        }

        return $errors;
    }

    /**
     * Returns any validation errors recorded during boot.
     *
     * @return array<string, array<string>>
     */
    public static function getValidationErrors(): array
    {
        self::boot();
        return self::$validationErrors;
    }

    /**
     * Registers a language driver configuration in the registry.
     *
     * @param array $config The language driver manifest.
     */
    public static function register(array $config): void
    {
        if (empty($config['id'])) {
            return;
        }

        $id = strtolower($config['id']);
        
        // Normalize worker capabilities
        if (!isset($config['worker'])) {
            $config['worker'] = [
                'daemon_supported' => isset($config['execution']['daemon']),
                'protocol' => 'spp-socket-2.0',
                'auth_required' => true,
            ];
        }

        self::$drivers[$id] = $config;

        $extensions = $config['extensions'] ?? [];
        foreach ($extensions as $ext) {
            $extKey = strtolower(ltrim($ext, '.'));
            self::$extensionMap[$extKey] = $id;
        }
    }

    /**
     * Retrieves a driver by language ID or file extension.
     *
     * @param string $langOrExt Language ID (e.g. 'python', 'rust') or extension (e.g. 'py', 'rs').
     * @return array|null Driver configuration array, or null if not found.
     */
    public static function getDriver(string $langOrExt): ?array
    {
        self::boot();
        $key = strtolower(ltrim($langOrExt, '.'));

        if (isset(self::$drivers[$key])) {
            return self::$drivers[$key];
        }

        if (isset(self::$extensionMap[$key])) {
            $langId = self::$extensionMap[$key];
            return self::$drivers[$langId] ?? null;
        }

        return null;
    }

    /**
     * Resolves driver and entry point information from a file path.
     *
     * @param string $filePath Path to the polyglot service file.
     * @return array|null Array with ['driver' => ..., 'lang' => ..., 'func' => ...] or null.
     */
    public static function resolveByPath(string $filePath): ?array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $driver = self::getDriver($ext);

        if (!$driver) {
            return null;
        }

        return [
            'driver' => $driver,
            'lang' => $driver['id'],
            'func' => $driver['entry_point_function'] ?? 'render_partial',
            'category' => $driver['category'] ?? 'interpreted',
        ];
    }

    /**
     * Returns all registered language drivers.
     *
     * @return array
     */
    public static function getAllDrivers(): array
    {
        self::boot();
        return self::$drivers;
    }

    /**
     * Validates whether the current on-disk cache is fresh.
     */
    public static function isCacheFresh(): bool
    {
        $cacheFile = self::getCacheFilePath();
        if (!file_exists($cacheFile)) {
            return false;
        }

        $data = json_decode((string)file_get_contents($cacheFile), true);
        if (!is_array($data) || ($data['version'] ?? '') !== self::CACHE_VERSION) {
            return false;
        }

        $cacheTimestamp = $data['timestamp'] ?? 0;
        $searchDirs = [
            SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'polyglot',
            SPP_APP_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'polyglot',
        ];

        foreach (array_unique($searchDirs) as $dir) {
            if (!is_dir($dir)) continue;
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.yml') ?: [];
            foreach ($files as $file) {
                if (filemtime($file) > $cacheTimestamp) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Discovers active runtimes on the current operating system for all registered drivers.
     * Consistently authoritative across CLI, Developer Studio, BridgeFactory, and Supervisor.
     *
     * @param bool $force Force re-scan and ignore cache
     * @return array
     */
    public static function discoverRuntimes(bool $force = false): array
    {
        self::boot();

        if (!$force && self::$cachedRuntimes !== null) {
            return self::$cachedRuntimes;
        }

        // 1. Try reading fresh cache
        $cacheFile = self::getCacheFilePath();
        if (!$force && self::isCacheFresh()) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['runtimes'])) {
                // Update dynamic enabled flags from status.yml
                $runtimes = $cached['runtimes'];
                foreach ($runtimes as $id => &$info) {
                    $info['enabled'] = self::isEnabled($id);
                }
                unset($info);
                self::$cachedRuntimes = $runtimes;
                return self::$cachedRuntimes;
            }
        }

        // 2. Perform Discovery Scan
        $runtimes = [];
        $isWindows = PHP_OS_FAMILY === 'Windows';

        foreach (self::$drivers as $id => $driver) {
            $discovery = $driver['discovery'] ?? [];
            $binaries = $isWindows 
                ? ($discovery['binaries']['windows'] ?? $discovery['binaries']['all'] ?? [$id])
                : ($discovery['binaries']['posix'] ?? $discovery['binaries']['all'] ?? [$id]);

            $foundPath = '';
            $version = 'Unknown';

            if (($driver['category'] ?? '') === 'dsl') {
                $foundPath = 'in-process';
                $version = 'In-Process Sandboxed Engine';
            } else {
                foreach ($binaries as $bin) {
                    $path = Platform::findBinary($bin);
                    if ($path) {
                        $foundPath = $path;
                        $verArg = $discovery['version_arg'] ?? '--version';
                        $rawVer = ProcessRunner::isSupported()
                            ? (string)(@shell_exec("\"{$path}\" {$verArg} 2>&1") ?: '')
                            : '';
                        if (!empty($discovery['version_regex'])) {
                            if (preg_match('/' . $discovery['version_regex'] . '/i', $rawVer, $m)) {
                                $version = $m[1];
                            }
                        } elseif (!empty($rawVer)) {
                            $version = trim(explode("\n", $rawVer)[0]);
                        }
                        break;
                    }
                }
            }

            $runtimes[$id] = [
                'id' => $id,
                'name' => $driver['name'] ?? ucfirst($id),
                'path' => $foundPath,
                'version' => $foundPath ? $version : 'Not found in PATH',
                'category' => $driver['category'] ?? 'interpreted',
                'extensions' => $driver['extensions'] ?? [],
                'enabled' => self::isEnabled($id),
                'worker' => $driver['worker'] ?? [
                    'daemon_supported' => isset($driver['execution']['daemon']),
                    'protocol' => 'spp-socket-2.0',
                    'auth_required' => true,
                ],
            ];
        }

        // 3. Persist Cache with Manifest Hashes and Version
        $cachePayload = [
            'version' => self::CACHE_VERSION,
            'timestamp' => time(),
            'os' => PHP_OS_FAMILY,
            'runtimes' => $runtimes,
        ];

        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        @file_put_contents($cacheFile, json_encode($cachePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        self::$cachedRuntimes = $runtimes;
        return $runtimes;
    }

    /**
     * Clears the runtime discovery cache.
     */
    public static function clearCache(): bool
    {
        self::$cachedRuntimes = null;
        $cacheFile = self::getCacheFilePath();
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }
        return true;
    }

    /**
     * Explicitly refreshes the registry manifests and runtime discovery cache.
     */
    public static function refresh(): array
    {
        self::clearCache();
        self::boot(true);
        return self::discoverRuntimes(true);
    }

    /**
     * Resolves the path to the language status YAML file.
     */
    public static function getStatusFilePath(): string
    {
        $appDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 4));
        if (is_dir($appDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'polyglot')) {
            return $appDir . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'polyglot' . DIRECTORY_SEPARATOR . 'status.yml';
        }
        $base = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 3);
        return $base . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'polyglot' . DIRECTORY_SEPARATOR . 'status.yml';
    }

    /**
     * Loads the list of disabled languages.
     *
     * @param bool $force Force reload from disk
     * @return array Array of disabled language IDs in lowercase
     */
    public static function loadStatus(bool $force = false): array
    {
        if (self::$disabledCache !== null && !$force) {
            return self::$disabledCache;
        }

        $filePath = self::getStatusFilePath();
        if (!file_exists($filePath)) {
            self::$disabledCache = [];
            return self::$disabledCache;
        }

        try {
            $parsed = Yaml::parseFile($filePath);
            $disabled = [];
            if (isset($parsed['disabled']) && is_array($parsed['disabled'])) {
                foreach ($parsed['disabled'] as $item) {
                    if (is_string($item)) {
                        $disabled[] = strtolower(trim($item));
                    }
                }
            } elseif (isset($parsed['languages']) && is_array($parsed['languages'])) {
                foreach ($parsed['languages'] as $lang => $state) {
                    if ($state === false || $state === 'disabled' || $state === 0) {
                        $disabled[] = strtolower(trim((string)$lang));
                    }
                }
            }
            self::$disabledCache = array_values(array_unique($disabled));
        } catch (\Throwable $e) {
            error_log("LanguageRegistry: Failed to parse status manifest: " . $e->getMessage());
            self::$disabledCache = [];
        }

        return self::$disabledCache;
    }

    /**
     * Persists the list of disabled languages to status.yml.
     *
     * @param array $disabled Array of disabled language IDs
     * @return bool True on success
     */
    public static function saveStatus(array $disabled): bool
    {
        $filePath = self::getStatusFilePath();
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $cleanDisabled = array_values(array_unique(array_filter(array_map('strtolower', array_map('trim', $disabled)))));
        sort($cleanDisabled);

        try {
            $content = "# SPP Universal Polyglot 2.0 Language Activation Manifest\n"
                     . "# Languages listed under 'disabled' are blocked from execution in SPP.\n"
                     . Yaml::dump(['disabled' => $cleanDisabled], 2, 2);

            $result = @file_put_contents($filePath, $content, LOCK_EX) !== false;
            if ($result) {
                self::$disabledCache = $cleanDisabled;
            }
            return $result;
        } catch (\Throwable $e) {
            error_log("LanguageRegistry: Failed to save status manifest: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a language or extension is currently enabled.
     *
     * @param string $langOrExt Language ID or extension
     * @return bool True if enabled, false if disabled
     */
    public static function isEnabled(string $langOrExt): bool
    {
        self::boot();
        $driver = self::getDriver($langOrExt);
        $langId = $driver ? $driver['id'] : strtolower(ltrim($langOrExt, '.'));

        $disabled = self::loadStatus();
        return !in_array($langId, $disabled, true);
    }

    /**
     * Enables a previously disabled language.
     *
     * @param string $langOrExt Language ID or extension
     * @return bool True on success
     */
    public static function enable(string $langOrExt): bool
    {
        self::boot();
        $driver = self::getDriver($langOrExt);
        $langId = $driver ? $driver['id'] : strtolower(ltrim($langOrExt, '.'));

        $disabled = self::loadStatus(true);
        $idx = array_search($langId, $disabled, true);
        if ($idx !== false) {
            unset($disabled[$idx]);
            return self::saveStatus(array_values($disabled));
        }

        return true;
    }

    /**
     * Disables a language from execution in SPP.
     *
     * @param string $langOrExt Language ID or extension
     * @return bool True on success
     */
    public static function disable(string $langOrExt): bool
    {
        self::boot();
        $driver = self::getDriver($langOrExt);
        $langId = $driver ? $driver['id'] : strtolower(ltrim($langOrExt, '.'));

        $disabled = self::loadStatus(true);
        if (!in_array($langId, $disabled, true)) {
            $disabled[] = $langId;
            return self::saveStatus($disabled);
        }

        return true;
    }

    /**
     * Toggles a language's enabled/disabled status.
     *
     * @param string $langOrExt Language ID or extension
     * @param bool|null $state Target state (null to invert)
     * @return bool New enabled state
     */
    public static function toggle(string $langOrExt, ?bool $state = null): bool
    {
        $current = self::isEnabled($langOrExt);
        $target = $state !== null ? $state : !$current;

        if ($target) {
            self::enable($langOrExt);
        } else {
            self::disable($langOrExt);
        }

        return $target;
    }
}
