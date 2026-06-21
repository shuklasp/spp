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
        $appname = $this->_attributes['appname'];
        $srcDir = $this->getAppSrcDir();
        $varPath = self::getAppConf('var_path', $appname) ?: 'var';
        $resolveAppPath = function (?string $path, string $default) use ($srcDir): string {
            $path = $path ?: $default;
            $normalized = str_replace('\\', '/', $path);
            $baseDir = str_starts_with($normalized, 'src/') || str_starts_with($normalized, '/src/')
                ? SPP_APP_DIR
                : $srcDir;

            return $this->resolvePath($path, $baseDir);
        };

        $this->data_dir  = $resolveAppPath(self::getAppConf('data_path', $appname), $varPath . '/data');
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
            $cacheFile = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'config.php';
            if (file_exists($cacheFile)) {
                $settings = require $cacheFile;
            } else {
                $path = SPP_ETC_DIR . '/global-settings.yml';
                $settings = file_exists($path) ? Yaml::parseFile($path) : [];

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
                    $appYml = $srcDir . '/' . $d . '/etc/app.yml';
                    if (file_exists($appYml)) {
                        try {
                            $appData = Yaml::parseFile($appYml);
                            if (is_array($appData) && $appData) {
                                $appSettings = array_merge($appData, $settings['apps'][$d] ?? []);

                                // Robustly prefix relative paths for dynamic apps so they resolve from src/
                                foreach (['etc_path', 'src_path', 'var_path', 'modules_path'] as $pk) {
                                    $val = $appSettings[$pk] ?? '';
                                    if ($val !== '' && strpos($val, ':') === false && strpos($val, '/') !== 0 && strpos($val, '\\') !== 0 && strpos($val, 'src/') !== 0) {
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
                        } catch (\Exception $e) {
                            error_log("SPP Discovery Error: Failed to parse $appYml: " . $e->getMessage());
                        }
                    }
                }
            } else {
                // error_log("SPP Discovery: srcDir not found or invalid: " . ($srcDir ?: 'NULL'));
            }
                if (!is_dir(dirname($cacheFile))) {
                    @mkdir(dirname($cacheFile), 0777, true);
                }
                @file_put_contents($cacheFile, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX);
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
            if (str_starts_with($etcPath, 'src/') || str_starts_with($etcPath, '/src/')) {
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
        $srcPath = self::getAppConf('src_path', $this->_attributes['appname']);
        if ($srcPath !== null && $srcPath !== '') {
            // src is always relative to SPP_APP_DIR (rooted at APP_BASE_DIR)
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

        $appName = $appName ?: (\SPP\Scheduler::getContext() ?: (self::getApp() ? self::getApp()->getName() : ''));
        if (!$appName || $appName === 'default') {
            $appName = \SPP\Scheduler::getContext();
        }

        $appPath = trim(self::getAppConf('base_url', $appName) ?? '', '/\\');
        if (!$appPath && $appName && $appName !== 'default') {
            $appPath = $appName;
        }

        $appRoot = $webRoot . ($appPath ? '/' . $appPath : '');
        return rtrim($appRoot, '/\\');
    }

    /**
     * Get the application's service container.
     */
    public function getContainer(): \SPP\Core\Container
    {
        return $this->container;
    }

    public static function boot(): void
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

        \SPP\Scheduler::detectAndEnforceContext();
        $context = \SPP\Scheduler::getContext();

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
