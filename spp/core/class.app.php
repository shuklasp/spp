<?php

namespace SPP;

use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/class.sppobject.php';

class App extends \SPP\SPPObject implements \Psr\Container\ContainerInterface
{
    private bool $modsloaded = false;
    private ?SPPError $errobj = null;
    private int $app_status = self::APP_EXEC;

    public const APP_EXEC    = 1;
    public const APP_WAITING = 2;
    public const APP_STOPPED = 3;
    public const APP_ERROR   = 4;

    // Directories
    private string $data_dir = '';
    private string $log_dir = '';
    private string $cache_dir = '';
    private string $tmp_dir = '';
    private string $conf_dir = '';
    private string $mod_dir = '';
    protected array $_getprops = ['type', 'appname'];
    protected \SPP\Core\Container $container;

    /** @var array<string, \SPP\App> */
    private static array $instances = [];

    public function __construct(string $appname = '', bool $handleerror = true, int $init_level = 4)
    {
        if ($appname === '') {
            $appname = 'default';
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $appname)) {
            throw new \SPP\SPPException("Invalid application name.");
        }

        $this->_attributes['appname'] = $appname;
        $this->container = new \SPP\Core\Container();

        $settings = self::getGlobalSettings();
        $this->_attributes['type'] = $settings['apps'][$appname]['type'] ?? 'native';

        if ($init_level >= 4) {
            $this->errobj = new SPPError($handleerror);
        }

        $this->initializeDirs();

        // Register status in global registry
        self::$instances[$appname] = $this;
        \SPP\Registry::register('__apps=>' . $appname . '=>status', self::APP_EXEC);

        if (class_exists('\\SPP\\Core\\Context\\ContextManager')) {
            \SPP\Core\Context\ContextManager::getOrCreate($appname, $this);
        }

        if ($init_level >= 1) {
            \SPP\Scheduler::regProc($this);
            if (!\SPP\Scheduler::hasContext() || \SPP\Scheduler::getContext() === $appname) {
                \SPP\Scheduler::setContext($appname);
            }
        }

        \SPP\SPPEvent::registerEvent('event_spp_app_init');
        $evtParams = new \SPP\EventParams($this);
        \SPP\SPPEvent::fireEvent('event_spp_app_init', $evtParams, function ($p) {
            // Default init: do nothing
        });
        if ($init_level >= 2) {
            $this->loadModules();
        }
        if ($init_level >= 3) {
            $oldcontext = \SPP\Scheduler::getContext();
            \SPP\Scheduler::setContext($appname);
            if (!SPPSession::sessionExists()) {
                $ssn = new SPPSession();
                $_SESSION['__' . $appname . '_sppsession'] = serialize($ssn);
            }
            \SPP\Scheduler::setContext($oldcontext);
        }

        \SPP\SPPEvent::registerDirs();
        \SPP\SPPEvent::scanHandlers();
    }

    private function initializeDirs(): void
    {
        $appname = $this->_attributes['appname'];
        $srcDir = $this->getAppSrcDir();
        $varPath = self::getAppConf('var_path', $appname) ?: 'var';
        $resolveAppPath = function (?string $path, string $default) use ($srcDir): string {
            $path = $path ?: $default;
            $normalized = str_replace('\\', '/', $path);
            $baseDir = str_starts_with($normalized, 'src/') || str_starts_with($normalized, '/src/') || str_starts_with($normalized, 'domains/') || str_starts_with($normalized, '/domains/')
                ? SPP_APP_DIR
                : $srcDir;

            return $this->resolvePath($path, $baseDir);
        };

        // Domain-scoped or user-home sppdata resolution
        $customDataDir = null;
        $activeHost = class_exists('\SPP\Core\Router\DomainRouter')
            ? (\SPP\Core\Router\DomainRouter::getMatchedHost() ?: ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null))
            : null;
        if ($activeHost && class_exists('\SPP\Core\Router\DomainRouter')) {
            $customDataDir = \SPP\Core\Router\DomainRouter::resolveUserDataDir($activeHost, $appname);
        } elseif (defined('SPP_DATA_DIR') && is_dir(SPP_DATA_DIR . '/global')) {
            $customDataDir = SPP_DATA_DIR . '/global/' . $appname;
        }

        if ($customDataDir) {
            $this->data_dir = $customDataDir . '/data';
            if (!is_dir($this->data_dir)) {
                @mkdir($this->data_dir, 0777, true);
                @chmod($this->data_dir, 0777);
            }
        } else {
            $this->data_dir  = $resolveAppPath(self::getAppConf('data_path', $appname), $varPath . '/data');
        }
        $this->log_dir   = $resolveAppPath(self::getAppConf('log_path', $appname), $varPath . '/logs');
        $this->cache_dir = $resolveAppPath(self::getAppConf('cache_path', $appname), $varPath . '/cache');
        $this->tmp_dir   = $resolveAppPath(self::getAppConf('tmp_path', $appname), $varPath . '/tmp');

        $this->conf_dir = $this->getAppConfDir();
        $this->mod_dir  = $resolveAppPath(self::getAppConf('modules_path', $appname), 'modules');
    }

    public static function getApp(string $appname = ''): \SPP\App
    {
        $context = ($appname === '') ? \SPP\Scheduler::getContext() : $appname;
        if (isset(self::$instances[$context])) {
            return self::$instances[$context];
        }
        return new \SPP\App($context);
    }

    public static function getInstance(string $appname = ''): ?\SPP\App
    {
        $context = ($appname === '') ? \SPP\Scheduler::getContext() : $appname;
        return self::$instances[$context] ?? null;
    }

    /**
     * Get global settings or a specific value using dot notation.
     *
     * @param string $key Optional dot-notation key (e.g. 'apps.default.base_url')
     * @return mixed
     */
    public static function getGlobalSettings(string $key = '', bool $reload = false): mixed
    {
        static $settings = null;
        if ($reload) {
            $settings = null;
        }
        if ($settings === null) {
            $cacheFile = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'config.php';
            if (!$reload && file_exists($cacheFile)) {
                $settings = require $cacheFile;
            } else {
                $path = SPP_ETC_DIR . '/global-settings.yml';
                if (!file_exists($path) && class_exists('\\SPP\\Core\\Config\\ConfigIsolationManager')) {
                    $path = \SPP\Core\Config\ConfigIsolationManager::ensureEffectiveConfig('global-settings.yml', SPP_ETC_DIR);
                }
                if (file_exists($path)) {
                    $rawContent = file_get_contents($path);
                    if (str_starts_with($rawContent, "\xEF\xBB\xBF")) {
                        $rawContent = substr($rawContent, 3);
                    }
                    $settings = Yaml::parse($rawContent) ?: [];
                } else {
                    $settings = [];
                }

            // Dynamic Discovery: Scan src/*/etc/app.yml for self-contained apps
            if (!isset($settings['apps'])) {
                $settings['apps'] = [];
            }

            // Skip discovery if explicitly requested (e.g. for lightweight XDB operations)
            if (defined('SPP_SKIP_DISCOVERY') && SPP_SKIP_DISCOVERY) {
                return $settings;
            }

            $srcDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src' : null;
            if ($srcDir && is_dir($srcDir)) {
                $dirs = array_diff(scandir($srcDir), ['.', '..']);
                foreach ($dirs as $d) {
                    if (file_exists($srcDir . '/' . $d . '/.uninstalled')) {
                        continue;
                    }
                    $appYml = $srcDir . '/' . $d . '/etc/app.yml';
                    $propsFile = $srcDir . '/' . $d . '/__spp_file_props.yml';
                    $appData = [];
                    if (file_exists($appYml)) {
                        try {
                            $rawYml = @file_get_contents($appYml);
                            $appData = ($rawYml !== false && $rawYml !== '') ? Yaml::parse($rawYml) : [];
                        } catch (\Exception $e) {
                            error_log("SPP Discovery Error: Failed to parse $appYml: " . $e->getMessage());
                        }
                    } elseif (file_exists($propsFile)) {
                        try {
                            $rawProps = @file_get_contents($propsFile);
                            $parsedProps = ($rawProps !== false && $rawProps !== '') ? Yaml::parse($rawProps) : [];
                            $dirProps = $parsedProps['directory'] ?? [];
                            $attrs = $dirProps['attributes'] ?? [];
                            if (!empty($dirProps['is_app']) || !empty($attrs['is_app']) || !empty($dirProps['is_app_dir'])) {
                                $appData = [
                                    'name' => $dirProps['app_name'] ?? $attrs['app_name'] ?? $d,
                                    'title' => $dirProps['title'] ?? $dirProps['description'] ?? ucfirst($d),
                                    'type' => $dirProps['app_type'] ?? $attrs['app_type'] ?? 'spp',
                                    'src_path' => 'src/' . $d,
                                    'data_dir' => $dirProps['data_dir'] ?? $attrs['data_dir'] ?? null,
                                    'index' => $dirProps['index'] ?? $attrs['index'] ?? 'index.php',
                                    'opt_in_sppdata' => !empty($dirProps['opt_in_sppdata']) || !empty($attrs['opt_in_sppdata']),
                                ];
                            }
                        } catch (\Throwable $e) {}
                    }
                    if (is_array($appData) && $appData) {
                        if (!empty($appData['uninstalled']) || (isset($appData['installed']) && $appData['installed'] === false)) {
                            continue;
                        }
                        $appSettings = array_merge($appData, $settings['apps'][$d] ?? []);

                        // Robustly prefix relative paths for dynamic apps so they resolve from src/
                        foreach (['etc_path', 'src_path', 'var_path', 'modules_path'] as $pk) {
                            $val = $appSettings[$pk] ?? '';
                            if ($val !== '' && strpos($val, ':') === false && strpos($val, '/') !== 0 && strpos($val, '\\') !== 0 && strpos($val, 'src/') !== 0 && !($pk === 'etc_path' && strpos($val, 'etc/') === 0)) {
                                $appSettings[$pk] = 'src/' . $d . '/' . trim($val, '/\\');
                            }
                        }

                        // Default src_path if still empty
                        if (empty($appSettings['src_path'])) {
                            $appSettings['src_path'] = 'src/' . $d;
                        }
                        // Default etc_path if still empty
                        if (empty($appSettings['etc_path'])) {
                            $appSettings['etc_path'] = 'src/' . $d . '/etc';
                        }

                        $settings['apps'][$d] = $appSettings;
                        // error_log("SPP Discovery: Found dynamic app '$d' at $appYml");
                    }
                }
            } else {
                // error_log("SPP Discovery: srcDir not found or invalid: " . ($srcDir ?: 'NULL'));
            }

            // Dynamic Discovery: Scan custom app_dir from etc/domains.yml or user home
            if (class_exists('\SPP\Core\Router\DomainRouter')) {
                \SPP\Core\Router\DomainRouter::loadConfig();
                $allDomains = \SPP\Core\Router\DomainRouter::getAllDomains();
                foreach ($allDomains as $domName => $dCfg) {
                    $customAppDir = $dCfg['app_dir'] ?? null;
                    if (!$customAppDir && !empty($dCfg['account']) && $dCfg['account'] !== 'default' && is_dir('/home/' . $dCfg['account'] . '/apps')) {
                        $customAppDir = '/home/' . $dCfg['account'] . '/apps';
                    }
                    if ($customAppDir && is_dir($customAppDir)) {
                        $uAppDirs = array_diff(scandir($customAppDir), ['.', '..']);
                        foreach ($uAppDirs as $d) {
                            $appPath = $customAppDir . '/' . $d;
                            if (!is_dir($appPath)) continue;

                            // Filter out hidden, system, web server metadata, and non-app directories
                            if (str_starts_with($d, '.') || str_starts_with($d, '_')) continue;
                            if (in_array(strtolower($d), ['cgi-bin', 'bin', 'vendor', 'node_modules', 'tmp', 'temp', 'cache', 'logs', 'error_log', 'old', 'bak', 'backup'], true)) continue;
                            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $d)) continue;

                            $appYml = $appPath . '/etc/app.yml';
                            $rootAppYml = $appPath . '/app.yml';
                            $propsFile = $appPath . '/__spp_file_props.yml';
                            $parentPropsFile = $customAppDir . '/__spp_file_props.yml';
                            $appData = [];
                            if (file_exists($appYml)) {
                                try {
                                    $parsed = Yaml::parseFile($appYml);
                                    if (is_array($parsed)) $appData = $parsed;
                                } catch (\Exception $e) {}
                            } elseif (file_exists($rootAppYml)) {
                                try {
                                    $parsed = Yaml::parseFile($rootAppYml);
                                    if (is_array($parsed)) $appData = $parsed;
                                } catch (\Exception $e) {}
                            } elseif (file_exists($propsFile)) {
                                try {
                                    $rawProps = @file_get_contents($propsFile);
                                    $parsedProps = ($rawProps !== false && $rawProps !== '') ? Yaml::parse($rawProps) : [];
                                    $dirProps = $parsedProps['directory'] ?? [];
                                    $attrs = $dirProps['attributes'] ?? [];
                                    if (!empty($dirProps['is_app']) || !empty($attrs['is_app']) || !empty($dirProps['is_app_dir'])) {
                                        $appData = [
                                            'name' => $dirProps['app_name'] ?? $attrs['app_name'] ?? $d,
                                            'title' => $dirProps['title'] ?? $dirProps['description'] ?? ucfirst($d),
                                            'type' => $dirProps['app_type'] ?? $attrs['app_type'] ?? 'spp',
                                            'src_path' => $appPath,
                                            'data_dir' => $dirProps['data_dir'] ?? $attrs['data_dir'] ?? null,
                                            'index' => $dirProps['index'] ?? $attrs['index'] ?? 'index.php',
                                            'opt_in_sppdata' => !empty($dirProps['opt_in_sppdata']) || !empty($attrs['opt_in_sppdata']),
                                        ];
                                    }
                                } catch (\Throwable $e) {}
                            } elseif (file_exists($parentPropsFile)) {
                                try {
                                    $rawParent = @file_get_contents($parentPropsFile);
                                    $parsedParent = ($rawParent !== false && $rawParent !== '') ? Yaml::parse($rawParent) : [];
                                    $dProps = $parsedParent['directories'][$d] ?? null;
                                    if ($dProps && (!empty($dProps['is_app']) || !empty($dProps['attributes']['is_app']))) {
                                        $appData = [
                                            'name' => $dProps['app_name'] ?? $d,
                                            'title' => $dProps['title'] ?? $dProps['description'] ?? ucfirst($d),
                                            'type' => $dProps['app_type'] ?? 'spp',
                                            'src_path' => $appPath,
                                            'data_dir' => $dProps['data_dir'] ?? null,
                                            'index' => $dProps['index'] ?? 'index.php',
                                            'opt_in_sppdata' => !empty($dProps['opt_in_sppdata']),
                                        ];
                                    }
                                } catch (\Throwable $e) {}
                            } elseif (file_exists($appPath . '/init.php') || file_exists($appPath . '/etc/config.yml')) {
                                $appData = ['name' => $d];
                            } else {
                                // Not a valid SPP application directory; skip
                                continue;
                            }

                            $appSettings = array_merge($appData, $settings['apps'][$d] ?? []);
                            if (empty($appSettings['src_path'])) $appSettings['src_path'] = $appPath;
                            if (empty($appSettings['etc_path'])) $appSettings['etc_path'] = $appPath . '/etc';
                            $appSettings['installed_domain'] = $domName;
                            $appSettings['is_domain_app'] = true;
                            $appSettings['domain_only'] = true;
                            if (!isset($appSettings['allowed_domains'])) {
                                $appSettings['allowed_domains'] = [$domName];
                            } elseif (!in_array($domName, (array)$appSettings['allowed_domains'], true)) {
                                $appSettings['allowed_domains'][] = $domName;
                            }
                            $settings['apps'][$d] = $appSettings;
                        }
                    }

                    // Register configured sub_apps and non-spp default_apps from domains.yml
                    if (!empty($dCfg['sub_apps']) && is_array($dCfg['sub_apps'])) {
                        foreach ($dCfg['sub_apps'] as $route => $subCfg) {
                            $subName = $subCfg['app_name'] ?? trim($route, '/');
                            if ($subName !== '' && !isset($settings['apps'][$subName])) {
                                $settings['apps'][$subName] = [
                                    'name' => $subName,
                                    'title' => $subCfg['title'] ?? ucfirst($subName),
                                    'type' => $subCfg['type'] ?? 'non_spp',
                                    'base_url' => '/' . trim($route, '/'),
                                    'src_path' => $subCfg['target_path'] ?? '',
                                    'data_dir' => $subCfg['data_dir'] ?? null,
                                    'index' => $subCfg['index'] ?? 'index.php',
                                    'opt_in_sppdata' => !empty($subCfg['opt_in_sppdata']),
                                    'installed_domain' => $domName,
                                    'is_domain_app' => true,
                                    'allowed_domains' => [$domName],
                                ];
                            }
                        }
                    }
                    if (is_array($dCfg['default_app'] ?? null)) {
                        $defName = $dCfg['default_app']['app_name'] ?? 'default';
                        if ($defName !== '' && !isset($settings['apps'][$defName])) {
                            $settings['apps'][$defName] = [
                                'name' => $defName,
                                'title' => $dCfg['default_app']['title'] ?? ucfirst($defName),
                                'type' => $dCfg['default_app']['type'] ?? 'non_spp',
                                'base_url' => '/',
                                'src_path' => $dCfg['default_app']['target_path'] ?? '',
                                'data_dir' => $dCfg['default_app']['data_dir'] ?? null,
                                'index' => $dCfg['default_app']['index'] ?? 'index.php',
                                'opt_in_sppdata' => !empty($dCfg['default_app']['opt_in_sppdata']),
                                'installed_domain' => $domName,
                                'is_domain_app' => true,
                                'allowed_domains' => [$domName],
                            ];
                        }
                    }
                }
            }

            // Dynamic Discovery: Scan domains/*/src/* for domain-specific applications
            $domainsDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/domains' : null;
            if ($domainsDir && is_dir($domainsDir)) {
                $domList = array_diff(scandir($domainsDir), ['.', '..']);
                foreach ($domList as $domName) {
                    $domSrc = $domainsDir . '/' . $domName . '/src';
                    if (!is_dir($domSrc)) continue;
                    $appDirs = array_diff(scandir($domSrc), ['.', '..']);
                    foreach ($appDirs as $d) {
                        $appPath = $domSrc . '/' . $d;
                        if (!is_dir($appPath)) continue;

                        // Filter out hidden, system, and non-app directories
                        if (str_starts_with($d, '.') || str_starts_with($d, '_')) continue;
                        if (in_array(strtolower($d), ['cgi-bin', 'bin', 'vendor', 'node_modules', 'tmp', 'temp', 'cache', 'logs', 'error_log', 'old', 'bak', 'backup'], true)) continue;
                        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $d)) continue;

                        $appYml = $appPath . '/etc/app.yml';
                        $rootAppYml = $appPath . '/app.yml';
                        $appData = [];
                        if (file_exists($appYml)) {
                            try {
                                $rawParsed = @file_get_contents($appYml);
                                $parsed = ($rawParsed !== false && $rawParsed !== '') ? Yaml::parse($rawParsed) : [];
                                if (is_array($parsed)) $appData = $parsed;
                            } catch (\Exception $e) {
                                error_log("SPP Discovery Error: Failed to parse $appYml: " . $e->getMessage());
                            }
                        } elseif (file_exists($rootAppYml)) {
                            try {
                                $parsed = Yaml::parseFile($rootAppYml);
                                if (is_array($parsed)) $appData = $parsed;
                            } catch (\Exception $e) {}
                        } elseif (file_exists($appPath . '/init.php') || file_exists($appPath . '/etc/config.yml')) {
                            $appData = ['name' => $d];
                        } else {
                            continue;
                        }
                        $appSettings = array_merge($appData, $settings['apps'][$d] ?? []);
                        foreach (['etc_path', 'src_path', 'var_path', 'modules_path'] as $pk) {
                            $val = $appSettings[$pk] ?? '';
                            if ($val !== '' && strpos($val, ':') === false && strpos($val, '/') !== 0 && strpos($val, '\\') !== 0 && strpos($val, 'domains/') !== 0 && strpos($val, 'src/') !== 0) {
                                $appSettings[$pk] = 'domains/' . $domName . '/src/' . $d . '/' . trim($val, '/\\');
                            }
                        }
                        if (empty($appSettings['src_path'])) $appSettings['src_path'] = 'domains/' . $domName . '/src/' . $d;
                        if (empty($appSettings['etc_path'])) $appSettings['etc_path'] = 'domains/' . $domName . '/src/' . $d . '/etc';
                        $appSettings['installed_domain'] = $domName;
                        $appSettings['is_domain_app'] = true;
                        $appSettings['domain_only'] = true;
                        if (!isset($appSettings['allowed_domains'])) {
                            $appSettings['allowed_domains'] = [$domName];
                        } elseif (!in_array($domName, (array)$appSettings['allowed_domains'], true)) {
                            $appSettings['allowed_domains'][] = $domName;
                        }
                        $settings['apps'][$d] = $appSettings;
                    }
                }
            }
                if (!is_dir(dirname($cacheFile))) {
                    @mkdir(dirname($cacheFile), 0777, true);
                }
                $tmp = $cacheFile . '.' . uniqid('cfg_', true) . '.tmp';
                if (@file_put_contents($tmp, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX) !== false) {
                    @rename($tmp, $cacheFile);
                }
            }

        }

        if ($key === '') {
            return $settings;
        }

        $parts = explode('.', $key);
        $value = $settings;
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return '';
            }
        }

        return $value;
    }

    public static function isDevMode(): bool
    {
        $profile = self::getGlobalSettings('profile');
        return is_string($profile) && strcasecmp($profile, 'dev') === 0;
    }

    public static function resetGlobalSettings(): void
    {
        self::getGlobalSettings('', true);
    }

    public static function getActiveApp(): string
    {
        return \SPP\Scheduler::getContext();
    }

    public static function getAppConf(string $key, string $appname = ''): mixed
    {
        $context = ($appname === '') ? \SPP\Scheduler::getContext() : $appname;
        $settings = self::getGlobalSettings();
        return $settings['apps'][$context][$key] ?? null;
    }

    public function isModsLoaded(): bool
    {
        return $this->modsloaded;
    }

    public function setStatus(int $status): void
    {
        if (in_array($status, [self::APP_EXEC, self::APP_STOPPED, self::APP_WAITING, self::APP_ERROR], true)) {
            $this->app_status = $status;
        } else {
            throw new \SPP\SPPException('Invalid application status.');
        }
    }

    public function getStatus(): int
    {
        return $this->app_status;
    }

    public function getLogDir(): string
    {
        return $this->log_dir;
    }
    public function getCacheDir(): string
    {
        return $this->cache_dir;
    }
    public function getTmpDir(): string
    {
        return $this->tmp_dir;
    }
    public function getConfDir(): string
    {
        return $this->conf_dir;
    }
    public function getModDir(): string
    {
        return $this->mod_dir;
    }
    public function getDataDir(): string
    {
        return $this->data_dir;
    }

    /**
     * Resolves a path. If relative, it is resolved relative to $baseDir.
     * If $baseDir is not provided, it falls back to SPP_APP_DIR.
     */
    public function resolvePath(?string $path, string $baseDir = ''): string
    {
        if (empty($path)) {
            return $baseDir;
        }
        if ($baseDir === '') {
            $baseDir = SPP_APP_DIR;
        }

        // Normalize both to avoid mismatches
        $path = str_replace('\\', '/', $path);
        $baseDir = str_replace('\\', '/', $baseDir);

        $procVersion = (PHP_OS === 'Linux' && is_readable('/proc/version')) ? (file_get_contents('/proc/version') ?: '') : '';
        $isWsl = $procVersion !== '' && (str_contains($procVersion, 'microsoft') || str_contains($procVersion, 'WSL'));

        // 1. Absolute Path Check
        if (str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':')) {
            // If absolute path is Windows-style but we are in WSL, convert to WSL
            if ($isWsl && strlen($path) > 1 && $path[1] === ':') {
                return '/mnt/' . strtolower($path[0]) . substr($path, 2);
            }
            return $path;
        }

        // 2. Relative to baseDir
        $res = rtrim($baseDir, '/') . '/' . ltrim($path, '/');

        // Final normalization for WSL if needed
        if ($isWsl && strlen($res) > 1 && $res[1] === ':') {
            $res = '/mnt/' . strtolower($res[0]) . substr($res, 2);
        }

        return $res;
    }

    public function getAppConfDir(): string
    {
        $appname = $this->_attributes['appname'];
        $etcPath = self::getAppConf('etc_path', $appname);
        if ($etcPath !== null && $etcPath !== '') {
            // If etc_path already contains the app directory prefix (discovery side-effect or explicit),
            // resolve from SPP_APP_DIR instead of getAppSrcDir() to avoid double nesting
            if (str_starts_with($etcPath, 'src/') || str_starts_with($etcPath, '/src/') || str_starts_with($etcPath, 'domains/') || str_starts_with($etcPath, '/domains/')) {
                return $this->resolvePath($etcPath, SPP_APP_DIR);
            }

            // Absolute/rooted app paths are resolved from SPP_APP_DIR.
            if (str_starts_with($etcPath, '/') || str_starts_with($etcPath, '\\') || str_starts_with($etcPath, 'etc/')) {
                return $this->resolvePath($etcPath, SPP_APP_DIR);
            }
            // Relative to src otherwise
            return $this->resolvePath($etcPath, $this->getAppSrcDir());
        }

        // Fallback: If src_path is set, use etc/ inside src dir.
        // Otherwise use global APP_ETC_DIR fallback.
        $srcPath = self::getAppConf('src_path', $appname);
        if ($srcPath !== null && $srcPath !== '') {
            return $this->resolvePath('etc', $this->getAppSrcDir());
        }

        return APP_ETC_DIR . SPP_DS . $appname;
    }

    public function getModsConfDir(): string
    {
        $appname = $this->_attributes['appname'];
        $modsConfPath = self::getAppConf('modsconf_path', $appname);
        if ($modsConfPath !== null && $modsConfPath !== '') {
            return $this->resolvePath($modsConfPath, SPP_APP_DIR);
        }

        // Default to modsconf subfolder in the resolved conf dir
        return $this->getAppConfDir() . SPP_DS . 'modsconf';
    }

    public function getAppSrcDir(): string
    {
        $appName = $this->_attributes['appname'];

        // Delegate to canonical PathResolver when available
        if (class_exists('\\SPP\\Core\\Domain\\PathResolver')) {
            $resolver = \SPP\Core\Domain\PathResolver::getInstance();
            if (class_exists('\\SPP\\Core\\Domain\\DomainContextResolver') && \SPP\Core\Domain\DomainContextResolver::has()) {
                $domId = \SPP\Core\Domain\DomainContextResolver::current()->getDomainId();
                $domAppPath = $resolver->getDomainAppPath($appName, $domId);
                if (is_dir($domAppPath)) {
                    return $domAppPath;
                }
            }

            $globalAppPath = $resolver->getGlobalAppPath($appName);
            if (is_dir($globalAppPath)) {
                return $globalAppPath;
            }
        }

        $srcPath = self::getAppConf('src_path', $appName);
        if ($srcPath !== null && $srcPath !== '') {
            // src is always relative to SPP_APP_DIR (rooted at APP_BASE_DIR)
            return $this->resolvePath($srcPath, SPP_APP_DIR);
        }
        return $this->resolvePath('src/' . $appName, SPP_APP_DIR);
    }


    public function getErrorObj(): ?SPPError
    {
        return $this->errobj;
    }

    public function getName(): string
    {
        return $this->_attributes['appname'];
    }

    public static function initSession(): void
    {
        $ssname = self::getSessionName();
        if (!SPPSession::sessionExists()) {
            $ssn = new SPPSession();
            $_SESSION[$ssname] = serialize($ssn);
        }
    }

    public static function killSession(): void
    {
        $ssname = self::getSessionName();
        if (SPPSession::sessionExists()) {
            unset($_SESSION[$ssname]);
        }
    }

    public static function getSessionName(): string
    {
        $context = \SPP\Scheduler::getContext();
        return '__' . $context . '_sppsession';
    }

    public function loadModules(): void
    {
        if (!$this->modsloaded) {
            $oldcontext = \SPP\Scheduler::getContext();
            \SPP\Scheduler::setContext($this->_attributes['appname']);
            \SPP\Module::loadAllModules();
            \SPP\Scheduler::setContext($oldcontext);
            $this->modsloaded = true;
        }
    }

    public function make(string $abstract, array $parameters = [])
    {
        if (method_exists($this->container, 'make')) {
            $method = 'make';
            return $this->container->{$method}($abstract, $parameters);
        }

        if ($parameters === []) {
            return $this->container->get($abstract);
        }

        return $this->makeWithParameters($abstract, $parameters);
    }

    public function bind(string $abstract, $concrete = null, bool $shared = false)
    {
        $this->container->bind($abstract, $concrete, $shared);
    }

    public function singleton(string $abstract, $concrete = null)
    {
        $this->container->singleton($abstract, $concrete);
    }

    public function get(string $id)
    {
        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return method_exists($this->container, 'has') ? $this->container->has($id) : false;
    }

    public function call($callable, array $parameters = [])
    {
        if (method_exists($this->container, 'call')) {
            $method = 'call';
            return $this->container->{$method}($callable, $parameters);
        }

        if (is_string($callable) && str_contains($callable, '@')) {
            [$class, $method] = explode('@', $callable, 2);
            $callable = [$this->container->get($class), $method];
        }

        if (is_array($callable) && is_string($callable[0] ?? null)) {
            $callable[0] = $this->container->get($callable[0]);
        }

        if (!is_callable($callable)) {
            throw new \SPP\SPPException('Invalid callable supplied to App::call().');
        }

        if (is_array($callable)) {
            $reflection = new \ReflectionMethod($callable[0], $callable[1]);
        } elseif (is_string($callable) && str_contains($callable, '::')) {
            $reflection = new \ReflectionMethod($callable);
        } elseif (is_object($callable) && !$callable instanceof \Closure) {
            $reflection = new \ReflectionMethod($callable, '__invoke');
        } else {
            $reflection = new \ReflectionFunction($callable);
        }

        return $callable(...$this->resolveParameters($reflection->getParameters(), $parameters));
    }

    private function makeWithParameters(string $abstract, array $parameters)
    {
        if (!class_exists($abstract)) {
            throw new \SPP\SPPException("Service not found: " . $abstract);
        }

        $reflector = new \ReflectionClass($abstract);
        if (!$reflector->isInstantiable()) {
            throw new \SPP\SPPException("Class {$abstract} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $abstract();
        }

        return $reflector->newInstanceArgs($this->resolveParameters($constructor->getParameters(), $parameters));
    }

    private function resolveParameters(array $reflectionParameters, array $parameters): array
    {
        $resolved = [];

        foreach ($reflectionParameters as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $parameters)) {
                $resolved[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();
            if ($type !== null && !$this->isPrimitiveType($type)) {
                $resolved[] = $this->resolveTypedParameter($type, $parameter);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            throw new \SPP\SPPException("Cannot resolve parameter: {$name}");
        }

        return $resolved;
    }

    private function isPrimitiveType(\ReflectionType $type): bool
    {
        if ($type instanceof \ReflectionNamedType) {
            return $type->isBuiltin();
        }

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [] as $unionType) {
            if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                return false;
            }
        }

        return true;
    }

    private function resolveTypedParameter(\ReflectionType $type, \ReflectionParameter $parameter): mixed
    {
        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            return $this->container->get($type->getName());
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                    try {
                        return $this->container->get($unionType->getName());
                    } catch (\Throwable $e) {
                        // Try the next class in the union before failing below.
                    }
                }
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        if ($type->allowsNull()) {
            return null;
        }

        throw new \SPP\SPPException("Cannot resolve parameter: {$parameter->getName()}");
    }

    /**
     * Resolves the absolute context-aware base URL for a given application sub-directory context.
     * Centralizes multi-tenant web routing calculation framework-wide.
     *
     * @param string|null $appName The application context name
     * @return string Fully qualified root URI prefix
     */
    public static function getBaseUrl(?string $appName = null): string
    {
        $webRoot = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $webRoot = rtrim($webRoot, '/\\');

        $appName = $appName ?: (\SPP\Scheduler::getContext() ?: (self::getInstance() ? self::getInstance()->getName() : ''));
        if (!$appName || $appName === 'default') {
            $appName = \SPP\Scheduler::getContext();
        }

        // If the application is the default app for the current domain context, it is served at root
        if ($appName && class_exists('\SPP\Core\Router\DomainRouter') && \SPP\Core\Router\DomainRouter::isDefaultApp($appName)) {
            return $webRoot;
        }

        $appPath = trim(self::getAppConf('base_url', $appName) ?? '', '/\\');
        if (!$appPath && $appName && $appName !== 'default') {
            $appPath = $appName;
        }

        $appRoot = $webRoot . ($appPath ? '/' . $appPath : '');
        return rtrim($appRoot, '/\\');
    }

    public static function hasUrlRewriting(): bool
    {
        if (php_sapi_name() === 'cli') {
            return true;
        }
        if (function_exists('apache_get_modules') && in_array('mod_rewrite', apache_get_modules())) {
            return true;
        }
        if (isset($_SERVER['IIS_UrlRewriteModule'])) {
            return true;
        }
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($reqUri, 'index.php') === false && strpos($scriptName, 'index.php') !== false) {
            return true;
        }
        if (defined('SPP_FORCE_CLEAN_URLS') && SPP_FORCE_CLEAN_URLS) {
            return true;
        }
        return false;
    }

    public static function url(string $path = '', ?string $appName = null, array $queryParams = []): string
    {
        return \SPP\Core\Url::to($path, $appName, $queryParams);
    }

    /**
     * Normalize an external URL to ensure an explicit scheme (https://).
     *
     * @param string|null $url
     * @param string $defaultScheme
     * @return string
     */
    public static function externalUrl(?string $url, string $defaultScheme = 'https'): string
    {
        return \SPP\Core\Url::external($url, $defaultScheme);
    }

    /**
     * Get the URL for a dynamically mapped asset alias.
     * @param string $module The module name (or 'core')
     * @param string $dirAlias The directory alias defined in the config
     * @param string $file The relative file path
     * @return string
     */
    public static function getAssetUrl(string $module, string $dirAlias, string $file): string
    {
        $webRoot = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $webRoot = rtrim($webRoot, '/\\');
        return $webRoot . '/sppasset/' . $module . '/' . $dirAlias . '/' . ltrim($file, '/');
    }

    /**
     * Retrieve a combined map of all registered asset aliases across core and modules.
     * @return array
     */
    public static function getAllAssets(): array
    {
        $cacheKey = 'spp_all_assets_map';
        if (\SPP\Module::isEnabled('sppcache') && class_exists('\SPPMod\SPPCache\SPPCacheManager')) {
            $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $allAssets = [];
        
        // Fetch core assets
        $coreAssets = self::getGlobalSettings('assets') ?? [];
        if (!empty($coreAssets)) {
            $allAssets['core'] = $coreAssets;
        }

        // Fetch module assets by scanning module.yml files
        $moduleFiles = glob(SPP_BASE_DIR . '/modules/*/*/module.yml');
        if (is_array($moduleFiles)) {
            foreach ($moduleFiles as $file) {
                $modname = basename(dirname($file));
                $config = \SPP\Module::getConfig('assets', $modname);
                if (!empty($config)) {
                    $allAssets[$modname] = $config;
                }
            }
        }

        if (\SPP\Module::isEnabled('sppcache') && class_exists('\SPPMod\SPPCache\SPPCacheManager')) {
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, $allAssets, 86400, ['assets']);
        }

        return $allAssets;
    }

    /**
     * Scan mapped asset directories and include CSS and JS files in the view.
     * @param string|null $modname If provided, includes only assets for this module (or 'core').
     */
    public static function includeAssets(?string $modname = null): void
    {
        $cacheKey = 'spp_included_assets_' . ($modname ?: 'all');
        if (\SPP\Module::isEnabled('sppcache') && class_exists('\SPPMod\SPPCache\SPPCacheManager')) {
            $cached = \SPPMod\SPPCache\SPPCacheManager::get($cacheKey);
            if ($cached !== false && is_array($cached)) {
                if (!empty($cached['css'])) {
                    foreach ($cached['css'] as $css) \SPPMod\SPPView\ViewPage::addCssIncludeFile($css);
                }
                if (!empty($cached['js'])) {
                    foreach ($cached['js'] as $js) \SPPMod\SPPView\ViewPage::addJsIncludeFile($js);
                }
                return;
            }
        }

        $assets = self::getAllAssets();
        if ($modname !== null) {
            $assets = isset($assets[$modname]) ? [$modname => $assets[$modname]] : [];
        }

        $cssList = [];
        $jsList = [];

        foreach ($assets as $mod => $dirs) {
            foreach ($dirs as $alias => $physicalPath) {
                $baseDir = null;
                if ($mod === 'core') {
                    $baseDir = SPP_BASE_DIR . '/../' . ltrim($physicalPath, '/\\');
                } else {
                    $moduleDir = \SPP\Module::getModuleDir($mod);
                    if ($moduleDir) {
                        $baseDir = $moduleDir . '/' . ltrim($physicalPath, '/\\');
                    }
                }

                if ($baseDir && is_dir($baseDir)) {
                    // Match .js and .css files
                    $files = glob($baseDir . '/*.[cj][sS]*');
                    if (is_array($files)) {
                        foreach ($files as $file) {
                            $filename = basename($file);
                            $url = self::getAssetUrl($mod, $alias, $filename);
                            if (preg_match('/\.css$/i', $filename)) {
                                $cssList[] = $url;
                                \SPPMod\SPPView\ViewPage::addCssIncludeFile($url);
                            } elseif (preg_match('/\.js$/i', $filename)) {
                                $jsList[] = $url;
                                \SPPMod\SPPView\ViewPage::addJsIncludeFile($url);
                            }
                        }
                    }
                }
            }
        }

        if (\SPP\Module::isEnabled('sppcache') && class_exists('\SPPMod\SPPCache\SPPCacheManager')) {
            \SPPMod\SPPCache\SPPCacheManager::set($cacheKey, ['css' => $cssList, 'js' => $jsList], 86400, ['assets']);
        }
    }

    /**
     * Get the application's service container.
     */
    public function getContainer(): \SPP\Core\Container
    {
        return $this->container;
    }

    public static function boot(?string $forcedContext = null): void
    {
        // Load .env configuration
        $envFile = SPP_APP_DIR . SPP_DS . '.env';
        if (file_exists($envFile) && class_exists('\SPP\Core\DotEnvLoader')) {
            \SPP\Core\DotEnvLoader::load($envFile);
        }

        // Register modern ignition-style error handler
        if (class_exists('\SPP\Core\SPPErrorHandler')) {
            \SPP\Core\SPPErrorHandler::register();
        }

        if (defined('SPP_DEBUG') && SPP_DEBUG && class_exists('\SPP\SPPError')) {
            set_exception_handler('\SPP\SPPError::exceptionHandler');

            // Initialize Debug metrics if active
            if (class_exists('\SPP\Core\Debug')) {
                \SPP\Core\Debug::start();
            }
        }

        if (php_sapi_name() !== 'cli') {
            if (session_status() === PHP_SESSION_NONE) {
                // 1. Check for Redis Session Driver
                $redisEnabled = \SPP\Module::getConfig('enabled', 'redis');
                if (($redisEnabled === true || $redisEnabled === '1' || $redisEnabled === 'true') && class_exists('\SPP\Core\RedisCache') && \SPP\Core\RedisCache::isAvailable()) {
                    session_set_save_handler(new \SPP\Core\RedisSessionHandler(), true);
                }
                // Secure session cookies
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                session_start();
            }
        } else {
            // In CLI mode, ensure $_SESSION is at least an empty array to prevent bridge/core failures
            if (!isset($_SESSION)) {
                $_SESSION = [];
            }
        }

        if ($forcedContext !== null && $forcedContext !== '') {
            \SPP\Scheduler::setContext($forcedContext);
            $context = $forcedContext;
        } else {
            \SPP\Scheduler::detectAndEnforceContext();
            $context = \SPP\Scheduler::getContext();
        }

        // Resolve App Type
        $appType = 'standard';
        $appConfig = self::getGlobalSettings('apps.' . $context) ?: [];
        $appType = $appConfig['type'] ?? 'standard';
        $customAppClass = $appConfig['app_class'] ?? null;

        $appClass = "\\App\\" . ucfirst($context) . "\\" . ucfirst($context) . "App";
        
        if ($customAppClass && class_exists($customAppClass)) {
            $app = new $customAppClass($context);
        } elseif (class_exists($appClass)) {
            $app = new $appClass($context);
        } else {
            $app = new \SPP\App($context);
        }

        // Bridge Configuration Export
        if (defined('SPP_BASE_DIR') && class_exists('\SPP\PolyglotBridge')) {
            \SPP\PolyglotBridge::setup();
        }

        \SPP\SPPEvent::registerEvent('spp_init');
        $appinit = \SPP\App::getGlobalSettings('apps.' . $context . '.app_init');
        if ($appinit !== '' && $appinit !== null) {
            $initFile = '';
            if (str_contains($appinit, '/') || str_contains($appinit, '\\')) {
                $initFile = SPP_APP_DIR . SPP_DS . ltrim($appinit, '/\\');
            } else {
                $srcPath = \SPP\App::getGlobalSettings("apps.{$context}.src_path");
                if ($srcPath !== null && $srcPath !== '') {
                    $initFile = SPP_APP_DIR . SPP_DS . rtrim($srcPath, '/\\') . SPP_DS . $appinit;
                } else {
                    $initFile = SPP_APP_DIR . SPP_DS . 'src' . SPP_DS . $context . SPP_DS . $appinit;
                }
            }
            if (file_exists($initFile)) {
                require_once $initFile;
            }
        }

        \SPP\SPPEvent::registerEvent('event_spp_module_install');

        // Theme registration must be handled by the app context's init script or the module itself

        // Perform Locale Negotiation & Translation Loading
        if (class_exists('\SPP\Core\LocaleNegotiator')) {
            \SPP\Core\LocaleNegotiator::negotiate();
        }

        register_shutdown_function(['\\SPP\\SPPEvent', 'persistTrace']);
    }
}
