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
        if (self::resolveInterfaceAliases($className)) {
            return null;
        }

        $path = explode('\\', $className);
        $class = array_pop($path);

        // 3. Core Class Search
        if ($file = self::resolveCoreClass($className, $path, $class)) {
            return $file;
        }

        // 4. Modules: SPPMod\*, ContribMod\*, AppMod\*
        if ($file = self::resolveModuleClass($className, $path, $class)) {
            return $file;
        }

        // EventHandlers\* mapping
        if ($file = self::resolveEventHandlersClass($className, $path, $class)) {
            return $file;
        }

        // 5. General SPP\* resolving to root & PSR Polyfills
        if ($file = self::resolvePsrClass($className, $path)) {
            return $file;
        }

        // 6. App\* mapping
        if ($file = self::resolveAppClass($className, $path, $class)) {
            return $file;
        }

        return null;
    }

    private static function resolveEventHandlersClass(string $className, array $path, string $class): ?string
    {
        if (strpos($className, 'EventHandlers\\') === 0) {
            $parts = $path;
            array_shift($parts); // Remove EventHandlers
            $subPath = !empty($parts) ? implode(DIRECTORY_SEPARATOR, $parts) . DIRECTORY_SEPARATOR : '';
            $search = [
                SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR . $subPath . $class . '.php',
                SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR . $subPath . $class . '.php',
                dirname(SPP_BASE_DIR) . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR . $subPath . $class . '.php'
            ];
            foreach ($search as $file) {
                if (file_exists($file)) return $file;
            }
        }
        return null;
    }

    private static function resolveInterfaceAliases(string $className): bool
    {
        // Safe PSR-4 Aliasing to Legacy class.*.php filenames
        $psr4LegacyMap = [
            'SPP\ResourceController' => 'SPP\Core\ResourceController',
            'SPP\Command' => 'SPP\CLI\Command',
        ];

        if (isset($psr4LegacyMap[$className])) {
            class_alias($psr4LegacyMap[$className], $className);
            return true;
        }

        if ($className === 'SPP\\CacheInterface' || $className === 'SPP\\MiddlewareInterface' || $className === 'SPP\\iModule' || $className === 'SPP\\Storage' || $className === 'SPP\\SmartStorage') {
            class_alias('\\SPP\\Core\\' . substr($className, 4), '\\' . $className);
            return true;
        }
        if ($className === 'SPP\\SmartData') {
            class_alias('\\SPP\\Core\\SmartStorage', '\\SPP\\SmartData');
            return true;
        }
        if (substr($className, -9) === 'Exception' && $className !== 'SPPException' && $className !== 'SPP\\SPPException') {
            $isSppException = strpos($className, 'SPP\\') === 0 || strpos($className, 'SPPMod\\') === 0 || strpos($className, 'SPP_') === 0;
            if (!$isSppException) {
                return false;
            }
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.sppexception.php';
            $systemExceptions = SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'sppsystemexceptions.php';
            if (file_exists($systemExceptions)) {
                require_once $systemExceptions;
            }
            if (!class_exists($className, false)) {
                class_alias('SPP\\SPPException', $className);
            }
            return true;
        }
        return false;
    }

    private static function resolveCoreClass(string $className, array $path, string $class): ?string
    {
        if (empty($path) || (count($path) >= 1 && $path[0] === 'SPP')) {
            $search_paths = [
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.' . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'int.' . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'interface.' . strtolower(str_replace('Interface', '', $class)) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'interfaces' . DIRECTORY_SEPARATOR . $class . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'middleware' . DIRECTORY_SEPARATOR . 'class.' . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'middleware' . DIRECTORY_SEPARATOR . $class . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . $class . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . $class . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . 'middleware' . DIRECTORY_SEPARATOR . $class . '.php',
                SPP_MODULES_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'sppcache' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $class . '.php',
                SPP_MODULES_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'sppcache' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $class . 'Driver.php',
                SPP_MODULES_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . 'sppcache' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'SPP' . $class . 'Manager.php'
            ];
            foreach ($search_paths as $file) {
                if (file_exists($file)) return $file;
            }
        }
        return null;
    }

    private static function resolveAppSrcPath(string $appName): string
    {
        $srcPath = '';
        if (class_exists('\\SPP\\App', false)) {
            $srcPath = (string)\SPP\App::getGlobalSettings("apps.{$appName}.src_path");
        }
        if (empty($srcPath)) {
            return SPP_APP_DIR . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $appName;
        }
        if (str_starts_with($srcPath, '/') || (strlen($srcPath) > 1 && $srcPath[1] === ':')) {
            return rtrim($srcPath, '/\\');
        }
        return SPP_APP_DIR . DIRECTORY_SEPARATOR . rtrim($srcPath, '/\\');
    }

    private static function resolveAppModPath(string $appName, string $baseSrc): string
    {
        $modPath = '';
        if (class_exists('\\SPP\\App', false)) {
            $modPath = (string)\SPP\App::getGlobalSettings("apps.{$appName}.modules_path");
        }
        if (empty($modPath)) {
            return $baseSrc . DIRECTORY_SEPARATOR . 'modules';
        }
        if (str_starts_with($modPath, '/') || (strlen($modPath) > 1 && $modPath[1] === ':')) {
            return rtrim($modPath, '/\\');
        }
        $normalized = str_replace('\\', '/', $modPath);
        if (str_starts_with($normalized, 'src/') || str_starts_with($normalized, '/src/')) {
            return SPP_APP_DIR . DIRECTORY_SEPARATOR . ltrim($modPath, '/\\');
        }
        return rtrim($baseSrc, '/\\') . DIRECTORY_SEPARATOR . ltrim($modPath, '/\\');
    }

    private static function resolveModuleClass(string $className, array $path, string $class): ?string
    {
        $prefix = $path[0] ?? '';

        if ($prefix === 'SPPMod' || $prefix === 'ContribMod' || $prefix === 'AppMod') {
            $parts = $path;
            array_shift($parts); // Remove prefix
            
            $modDir = null;

            if ($prefix === 'SPPMod') {
                if (empty($parts)) return null;
                $mod = strtolower(array_shift($parts));
                $modDir = SPP_MODULES_DIR . DIRECTORY_SEPARATOR . 'spp' . DIRECTORY_SEPARATOR . $mod;

            } elseif ($prefix === 'ContribMod') {
                if (empty($parts)) return null;
                $mod = strtolower(array_shift($parts));
                $modDir = SPP_MODULES_DIR . DIRECTORY_SEPARATOR . 'contrib' . DIRECTORY_SEPARATOR . $mod;
            } elseif ($prefix === 'AppMod') {
                if (count($parts) < 2) return null;
                $appName = strtolower(array_shift($parts));
                $mod = strtolower(array_shift($parts));
                
                $baseSrc = self::resolveAppSrcPath($appName);
                $modDir = self::resolveAppModPath($appName, $baseSrc) . DIRECTORY_SEPARATOR . $mod;
            }

            if ($modDir && is_dir($modDir)) {
                return self::resolveModuleFromDir($modDir, $class, $parts);
            }
        }
        return null;
    }

    private static function resolvePsrClass(string $className, array $path): ?string
    {
        if (strpos($className, 'SPP\\') === 0 && strpos($className, 'SPPMod\\') !== 0 && strpos($className, 'SPP\\Core\\') !== 0) {
            $parts = $path;
            array_shift($parts); // Remove SPP
            $file = SPP_BASE_DIR . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts) . '.php';
            if (file_exists($file)) return $file;
        }

        if (strpos($className, 'Psr\\') === 0) {
            $file = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
            if (file_exists($file)) return $file;
        }

        return null;
    }

    private static function resolveAppClass(string $className, array $path, string $class): ?string
    {
        if (strpos($className, 'App\\') !== 0) {
            return null;
        }
        
        $fullPath = explode('\\', $className);
        
        if (count($fullPath) >= 3) {
            $appName = strtolower($fullPath[1]);
            $baseSrc = self::resolveAppSrcPath($appName);

            if (count($fullPath) >= 4) {
                $type = strtolower($fullPath[2]);
                $name = strtolower($fullPath[3]);

                $file = '';
                if ($type === 'entities') {
                    $file = $baseSrc . DIRECTORY_SEPARATOR . 'entities' . DIRECTORY_SEPARATOR . 'entity.' . $name . '.php';
                } elseif ($type === 'components') {
                    $file = $baseSrc . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $fullPath[3] . '.php';
                } elseif ($type === 'serv') {
                    $file = $baseSrc . DIRECTORY_SEPARATOR . 'serv' . DIRECTORY_SEPARATOR . $fullPath[3] . '.php';
                } else {
                    // General PSR-4 fallback within the app's src directory
                    $remaining = array_slice($fullPath, 2);
                    $file = $baseSrc . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $remaining) . '.php';
                }

                if ($file && file_exists($file)) return $file;
            } elseif (count($fullPath) === 3) {
                $classNamePart = $fullPath[2];
                $file = $baseSrc . DIRECTORY_SEPARATOR . $classNamePart . '.php';
                if (file_exists($file)) return $file;
            }
        }
        return null;
    }

    private static function resolveModuleFromDir(string $modDir, string $class, array $parts): ?string
    {
        $relPath = '';
        if (!empty($parts)) {
            $relPath = implode(DIRECTORY_SEPARATOR, $parts) . DIRECTORY_SEPARATOR;
        }

        $lowerClass = strtolower($class);
        $searchFiles = [
            $modDir . DIRECTORY_SEPARATOR . $relPath . 'class.' . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . 'int.' . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . 'trait.' . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . $class . '.php',
            $modDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relPath . $class . '.php',
            $modDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relPath . $lowerClass . '.php'
        ];

        foreach ($searchFiles as $file) {
            if (file_exists($file)) return $file;
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
