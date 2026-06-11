<?php

namespace SPP\Core;

/**
 * Class Autoloader
 * High-performance native classmap autoloader for SPP, minimizing Composer dependency.
 */
class Autoloader
{
    private static array $classMap = [];
    private static string $cacheFile = '';
    private static bool $mapChanged = false;

    public static function register(): void
    {
        if (!defined('SPP_APP_DIR')) {
            return; // Not fully booted yet
        }

        self::$cacheFile = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'classmap.php';

        if (file_exists(self::$cacheFile)) {
            self::$classMap = require self::$cacheFile;
        }

        spl_autoload_register([self::class, 'loadClass'], true, true);
        
        // Write the map back on shutdown if changed
        register_shutdown_function([self::class, 'saveMap']);
    }

    public static function loadClass(string $className): bool
    {
        // Check cache first
        if (isset(self::$classMap[$className])) {
            $file = self::$classMap[$className];
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
            unset(self::$classMap[$className]); // File deleted, remove from map
        }

        $file = self::resolveClass($className);
        if ($file && file_exists($file)) {
            self::$classMap[$className] = $file;
            self::$mapChanged = true;
            require_once $file;
            return true;
        }

        return false;
    }

    private static function resolveClass(string $className): ?string
    {
        // 1. Interface Compatibility Aliases (handle early)
        if ($className === 'SPP\\CacheInterface' || $className === 'SPP\\MiddlewareInterface' || $className === 'SPP\\iModule') {
            class_alias('\\SPP\\Core\\' . substr($className, 4), '\\' . $className);
            return null; // Don't cache alias creation as a file path
        }
        if (substr($className, -9) === 'Exception' && $className !== 'SPPException' && $className !== 'SPP\\SPPException') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.sppexception.php';
            $systemExceptions = SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'sppsystemexceptions.php';
            if (file_exists($systemExceptions)) {
                require_once $systemExceptions;
            }
            if (!class_exists($className, false)) {
                class_alias('SPP\\SPPException', $className);
            }
            return null;
        }

        // 2. Core Migration Aliasing
        $coreToModuleMap = [
            'SPP\\Cache' => 'sppcache/src/SPPCacheManager.php',
            'SPP\\Core\\FileCache' => 'sppcache/src/FileCacheDriver.php',
            'SPP\\Core\\RedisCache' => 'sppcache/src/RedisCacheDriver.php',
            'SPP\\Core\\Queue' => 'sppqueue/src/SPPQueue.php',
            'SPP\\Core\\SPPJobInterface' => 'sppqueue/src/SPPJobInterface.php',
            'SPP\\Core\\AppLogger' => 'spplogger/src/AppLogger.php',
            'SPP\\Core\\PsrLoggerAdapter' => 'spplogger/src/PsrLoggerAdapter.php',
            'SPP\\Core\\RequestLogger' => 'spplogger/src/RequestLogger.php',
            'SPP\\Core\\Storage' => 'sppstorage/src/SPPStorage.php',
            'SPP\\Core\\WorkflowManager' => 'sppworkflow/src/SPPWorkflowManager.php',
            'SPP\\Core\\DotEnvLoader' => 'sppenv/src/DotEnvLoader.php',
        ];
        if (isset($coreToModuleMap[$className])) {
            return SPP_MODULES_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . $coreToModuleMap[$className];
        }

        $path = explode('\\', $className);
        $class = array_pop($path);

        // 3. Core Class Search
        if (empty($path) || (count($path) >= 1 && $path[0] === 'SPP')) {
            $search_paths = [
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.' . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'int.' . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'interface.' . strtolower(str_replace('Interface', '', $class)) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'interfaces' . DIRECTORY_SEPARATOR . $class . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'middleware' . DIRECTORY_SEPARATOR . 'class.' . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . strtolower($class) . '.php'
            ];
            foreach ($search_paths as $file) {
                if (file_exists($file)) return $file;
            }
        }

        // 4. Modules: SPPMod\*
        if (strpos($className, 'SPPMod\\') === 0) {
            $parts = explode('\\', $className);
            array_shift($parts); // Remove SPPMod
            $mod = strtolower(array_shift($parts));

            // Module Consolidation Aliasing
            $aliasMap = [
                'sppentity'  => 'sppdb',
                'sppinterdb' => 'sppdb',
                'sppajax'    => 'sppapi',
                'sppblade'   => 'drishyam',
                'sppux'      => 'drishyam'
            ];
            if (isset($aliasMap[$mod])) {
                $mod = $aliasMap[$mod];
            }

            $remaining = $parts;
            $class = array_pop($remaining);

            // Buckets
            foreach (['spp', 'school'] as $bucket) {
                $modDir = SPP_MODULES_DIR . DIRECTORY_SEPARATOR . $bucket . DIRECTORY_SEPARATOR . $mod;
                if (is_dir($modDir)) {
                    if ($file = self::resolveModuleFromDir($modDir, $class, $parts)) return $file;
                }
            }

            // App-specific modules
            if (class_exists('\\SPP\\Scheduler', false)) {
                $ctx = \SPP\Scheduler::getContext();
                if ($ctx && $ctx !== '') {
                    $srcPath = \SPP\App::getAppConf('src_path', $ctx) ?? ('src' . DIRECTORY_SEPARATOR . $ctx);
                    $appModDir = SPP_APP_DIR . DIRECTORY_SEPARATOR . $srcPath . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $mod;
                    if (is_dir($appModDir)) {
                        if ($file = self::resolveModuleFromDir($appModDir, $class, $parts)) return $file;
                    }
                }
            }
        }

        // 5. General SPP\* resolving to root
        if (strpos($className, 'SPP\\') === 0 && strpos($className, 'SPPMod\\') !== 0 && strpos($className, 'SPP\\Core\\') !== 0) {
            $parts = explode('\\', $className);
            array_shift($parts); // Remove SPP
            $file = SPP_BASE_DIR . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
            if (file_exists($file)) return $file;
        }

        // 6. Polyfill PSR Autoloader
        if (strpos($className, 'Psr\\') === 0) {
            $file = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
            if (file_exists($file)) return $file;
        }

        // 7. App\* mapping
        if (strpos($className, 'App\\') === 0) {
            $parts = explode('\\', $className);
            if (count($parts) >= 3) {
                $appName = strtolower($parts[1]);
                $srcPath = '';
                if (class_exists('\\SPP\\App', false)) {
                    $srcPath = \SPP\App::getGlobalSettings("apps.{$appName}.src_path");
                }
                if ($srcPath !== null && $srcPath !== '') {
                    $baseSrc = SPP_APP_DIR . DIRECTORY_SEPARATOR . rtrim($srcPath, '/\\');
                } else {
                    $baseSrc = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $appName;
                }

                if (count($parts) >= 4) {
                    $type = strtolower($parts[2]);
                    $name = strtolower($parts[3]);

                    $file = '';
                    if ($type === 'entities') {
                        $file = $baseSrc . DIRECTORY_SEPARATOR . 'entities' . DIRECTORY_SEPARATOR . 'entity.' . $name . '.php';
                    } elseif ($type === 'components') {
                        $file = $baseSrc . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $parts[3] . '.php';
                    } elseif ($type === 'serv') {
                        $file = $baseSrc . DIRECTORY_SEPARATOR . 'serv' . DIRECTORY_SEPARATOR . $parts[3] . '.php';
                    } else {
                        // General PSR-4 fallback within the app's src directory
                        $remaining = array_slice($parts, 2);
                        $file = $baseSrc . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $remaining) . '.php';
                    }

                    if ($file && file_exists($file)) return $file;
                } elseif (count($parts) === 3) {
                    $classNamePart = $parts[2];
                    $file = $baseSrc . DIRECTORY_SEPARATOR . $classNamePart . '.php';
                    if (file_exists($file)) return $file;
                }
            }
        }

        return null;
    }

    private static function resolveModuleFromDir(string $modDir, string $class, array $parts): ?string
    {
        // Legacy class.<name>.php at root
        $legacyFile = $modDir . DIRECTORY_SEPARATOR . 'class.' . strtolower($class) . '.php';
        if (file_exists($legacyFile)) return $legacyFile;
        
        // Fallback: strip underscores for legacy names (e.g. SPP_Logger -> class.spplogger.php)
        $legacyFileNoUnderscore = $modDir . DIRECTORY_SEPARATOR . 'class.' . str_replace('_', '', strtolower($class)) . '.php';
        if (file_exists($legacyFileNoUnderscore)) return $legacyFileNoUnderscore;
        
        // Interfaces
        $interfaceFile = $modDir . DIRECTORY_SEPARATOR . 'int.' . strtolower($class) . '.php';
        if (file_exists($interfaceFile)) return $interfaceFile;

        // PSR-4 style in src/ and root
        if (!empty($parts)) {
            $relPath = implode(DIRECTORY_SEPARATOR, $parts) . '.php';
            $psrPaths = [
                $modDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relPath,
                $modDir . DIRECTORY_SEPARATOR . $relPath
            ];
            foreach ($psrPaths as $psrPath) {
                if (file_exists($psrPath)) return $psrPath;
            }
        }
        return null;
    }

    public static function saveMap(): void
    {
        if (self::$mapChanged && self::$cacheFile) {
            $dir = dirname(self::$cacheFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $content = "<?php\nreturn " . var_export(self::$classMap, true) . ";\n";
            @file_put_contents(self::$cacheFile, $content, LOCK_EX);
            self::$mapChanged = false;
        }
    }
}
