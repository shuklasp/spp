<?php
namespace SPP;

use Symfony\Component\Yaml\Yaml;

class App extends \SPP\SPPObject
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

        if ($init_level >= 1) {
            \SPP\Scheduler::regProc($this);
            \SPP\Scheduler::setContext($appname);
        }

        \SPP\SPPEvent::registerEvent('event_spp_app_init');
        \SPP\SPPEvent::fireEvent('event_spp_app_init', $this, function($app) {
            // Default init: do nothing
        });
        if ($init_level >= 2) {
            $this->loadModules();
        }
        if ($init_level >= 3) {
            if (!SPPSession::sessionExists()) {
                $ssn = new SPPSession();
                $_SESSION['__' . $appname . '_sppsession'] = serialize($ssn);
            }
        }

        \SPP\SPPEvent::registerDirs();
        \SPP\SPPEvent::scanHandlers();
    }

    private function initializeDirs(): void
    {
        $srcDir = $this->getAppSrcDir();
        $this->data_dir  = $this->resolvePath($this->getAppConf('data_path') ?: 'var/data', $srcDir);
        $this->log_dir   = $this->resolvePath($this->getAppConf('log_path') ?: 'var/logs', $srcDir);
        $this->cache_dir = $this->resolvePath($this->getAppConf('cache_path') ?: 'var/cache', $srcDir);
        $this->tmp_dir   = $this->resolvePath($this->getAppConf('tmp_path') ?: 'var/tmp', $srcDir);
        
        $this->conf_dir = $this->getAppConfDir();
        $this->mod_dir  = $this->resolvePath($this->getAppConf('modules_path') ?: 'modules', $srcDir);
    }

    public static function getApp(string $appname = ''): \SPP\App
    {
        $context = ($appname === '') ? \SPP\Scheduler::getContext() : $appname;
        if (isset(self::$instances[$context])) {
            return self::$instances[$context];
        }
        return new \SPP\App($context);
    }

    /**
     * Get global settings or a specific value using dot notation.
     *
     * @param string $key Optional dot-notation key (e.g. 'apps.default.base_url')
     * @return mixed
     */
    public static function getGlobalSettings(string $key = ''): mixed
    {
        static $settings = null;
        if ($settings === null) {
            $path = SPP_ETC_DIR . '/global-settings.yml';
            $settings = file_exists($path) ? Yaml::parseFile($path) : [];
            
            // Dynamic Discovery: Scan src/*/etc/app.yml for self-contained apps
            if (!isset($settings['apps'])) $settings['apps'] = [];

            // Skip discovery if explicitly requested (e.g. for lightweight XDB operations)
            if (defined('SPP_SKIP_DISCOVERY') && SPP_SKIP_DISCOVERY) {
                return $settings;
            }
            
            $srcDir = defined('SPP_APP_DIR') ? SPP_APP_DIR . '/src' : null;
            if ($srcDir && is_dir($srcDir)) {
                $dirs = array_diff(scandir($srcDir), ['.', '..']);
                foreach ($dirs as $d) {
                    $appYml = $srcDir . '/' . $d . '/etc/app.yml';
                    if (file_exists($appYml)) {
                        try {
                            $appData = Yaml::parseFile($appYml);
                            if ($appData) {
                                // Robustly prefix relative paths for dynamic apps so they resolve from src/
                                foreach (['etc_path', 'src_path', 'var_path', 'modules_path'] as $pk) {
                                    $val = $settings['apps'][$d][$pk] ?? '';
                                    if ($val !== '' && strpos($val, ':') === false && strpos($val, '/') !== 0 && strpos($val, '\\') !== 0 && strpos($val, 'src/') !== 0) {
                                        $settings['apps'][$d][$pk] = 'src/' . $d . '/' . trim($val, '/\\');
                                    }
                                }
                                
                                // Default src_path if still empty
                                if (empty($settings['apps'][$d]['src_path'])) $settings['apps'][$d]['src_path'] = 'src/' . $d;
                                // Default etc_path if still empty
                                if (empty($settings['apps'][$d]['etc_path'])) $settings['apps'][$d]['etc_path'] = 'src/' . $d . '/etc';

                                $settings['apps'][$d] = array_merge($appData, $settings['apps'][$d] ?? []);
                                error_log("SPP Discovery: Found dynamic app '$d' at $appYml");
                            }
                        } catch (\Exception $e) {
                            error_log("SPP Discovery Error: Failed to parse $appYml: " . $e->getMessage());
                        }
                    }
                }
            } else {
                error_log("SPP Discovery: srcDir not found or invalid: " . ($srcDir ?: 'NULL'));
            }

            // Fail-safe for Lekhak if discovery had issues
            if (!isset($settings['apps']['lekhak']) && is_dir($srcDir . '/lekhak')) {
                $settings['apps']['lekhak'] = [
                    'base_url' => '/lekhak',
                    'etc_path' => 'src/lekhak/etc',
                    'src_path' => 'src/lekhak',
                    'var_path' => 'src/lekhak/var',
                    'admin_icon' => '🖋️',
                    'admin_title' => 'Lekhak CMS'
                ];
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

    public function getLogDir(): string { return $this->log_dir; }
    public function getCacheDir(): string { return $this->cache_dir; }
    public function getTmpDir(): string { return $this->tmp_dir; }
    public function getConfDir(): string { return $this->conf_dir; }
    public function getModDir(): string { return $this->mod_dir; }
    public function getDataDir(): string { return $this->data_dir; }

    /**
     * Resolves a path. If relative, it is resolved relative to $baseDir.
     * If $baseDir is not provided, it falls back to SPP_APP_DIR.
     */
    public function resolvePath(?string $path, string $baseDir = ''): string
    {
        if (empty($path)) return $baseDir;
        if ($baseDir === '') $baseDir = SPP_APP_DIR;

        // 1. Absolute Path Check
        if (str_starts_with($path, '/') || str_starts_with($path, '\\') || (strlen($path) > 1 && $path[1] === ':')) {
            return $path;
        }

        // 2. Relative to baseDir
        return rtrim($baseDir, '/\\') . SPP_DS . ltrim($path, '/\\');
    }

    public function getAppConfDir(): string
    {
        $etcPath = self::getAppConf('etc_path', $this->_attributes['appname']);
        if ($etcPath !== null && $etcPath !== '') {
            return $this->resolvePath($etcPath, SPP_APP_DIR);
        }
        return $this->resolvePath('etc', $this->getAppSrcDir());
    }
    
    public function getModsConfDir(): string
    {
        $modsConfPath = self::getAppConf('modsconf_path', $this->_attributes['appname']);
        if ($modsConfPath !== null && $modsConfPath !== '') {
            return $this->resolvePath($modsConfPath, SPP_APP_DIR);
        }
        return $this->resolvePath('etc/modsconf', $this->getAppSrcDir());
    }

    public function getAppSrcDir(): string
    {
        $srcPath = self::getAppConf('src_path', $this->_attributes['appname']);
        if ($srcPath !== null && $srcPath !== '') {
            return $this->resolvePath($srcPath, SPP_APP_DIR);
        }
        return $this->resolvePath('src/' . $this->_attributes['appname'], SPP_APP_DIR);
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
        if (SPPSession::sessionExists()) unset($_SESSION[$ssname]);
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
        return $this->container->make($abstract, $parameters);
    }

    public function bind(string $abstract, $concrete = null, bool $shared = false)
    {
        $this->container->bind($abstract, $concrete, $shared);
    }

    public function singleton(string $abstract, $concrete = null)
    {
        $this->container->singleton($abstract, $concrete);
    }

    public function call($callable, array $parameters = [])
    {
        return $this->container->call($callable, $parameters);
    }

    /**
     * Get the application's service container.
     */
    public function getContainer(): \SPP\Core\Container
    {
        return $this->container;
    }
}
