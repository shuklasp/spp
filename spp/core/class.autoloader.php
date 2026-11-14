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

        $osSuffix = (DIRECTORY_SEPARATOR === '/') ? '_unix' : '_win';
        self::$cacheFile = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'classmap' . $osSuffix . '.php';

        $appDir = defined('SPP_APP_DIR') ? str_replace('\\', '/', SPP_APP_DIR) : '';

        if (file_exists(self::$cacheFile)) {
            try {
                $map = @include self::$cacheFile;
                if (is_array($map)) {
                    $cachedRoot = isset($map['__root']) ? str_replace('\\', '/', (string)$map['__root']) : null;
                    if ($cachedRoot !== null && $appDir !== '' && $cachedRoot !== $appDir) {
                        // Invalidate stale classmap from a different machine or path (e.g. deployed to remote host)
                        self::$classMap = [];
                        @unlink(self::$cacheFile);
                    } else {
                        // Check if entries contain foreign absolute paths
                        $isForeign = false;
                        foreach ($map as $k => $v) {
                            if ($k === '__root') continue;
                            if (is_string($v) && (str_starts_with($v, '/') || str_contains($v, ':\\'))) {
                                $normV = str_replace('\\', '/', $v);
                                if ($appDir !== '' && !str_starts_with($normV, $appDir)) {
                                    $isForeign = true;
                                    break;
                                }
                            }
                        }
                        if ($isForeign) {
                            self::$classMap = [];
                            @unlink(self::$cacheFile);
                        } else {
                            self::$classMap = $map;
                        }
                    }
                } else {
                    self::$classMap = [];
                    @unlink(self::$cacheFile);
                }
            } catch (\Throwable $e) {
                self::$classMap = [];
                @unlink(self::$cacheFile);
            }
        } elseif (file_exists(SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'classmap.php')) {
            $legacyFile = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR . 'classmap.php';
            try {
                $map = @include $legacyFile;
                if (is_array($map)) {
                    self::$classMap = $map;
                }
            } catch (\Throwable $e) {
                @unlink($legacyFile);
            }
        }

        spl_autoload_register([self::class, 'loadClass'], true, true);
        
        // Write the map back on shutdown if changed
        register_shutdown_function([self::class, 'saveMap']);
    }

    public static function loadClass(string $className): bool
    {
        $syncAlias = function (string $cls) {
            if (str_starts_with($cls, 'App\\')) {
                $alt = substr($cls, 4);
                if (class_exists($cls, false) && !class_exists($alt, false)) {
                    class_alias($cls, $alt);
                } elseif (class_exists($alt, false) && !class_exists($cls, false)) {
                    class_alias($alt, $cls);
                }
            } else {
                $alt = 'App\\' . $cls;
                if (class_exists($cls, false) && !class_exists($alt, false)) {
                    class_alias($cls, $alt);
                } elseif (class_exists($alt, false) && !class_exists($cls, false)) {
                    class_alias($alt, $cls);
                }
            }
        };

        // Check cache first
        if (isset(self::$classMap[$className])) {
            $file = self::$classMap[$className];
            if (is_string($file) && str_starts_with($file, './')) {
                $file = SPP_APP_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, substr($file, 2));
            }
            $appDir = defined('SPP_APP_DIR') ? str_replace('\\', '/', SPP_APP_DIR) : '';
            $normFile = str_replace('\\', '/', (string)$file);

            // Guard against foreign absolute paths violating open_basedir
            if ($appDir !== '' && (str_starts_with($normFile, '/') || str_contains($normFile, ':\\')) && !str_starts_with($normFile, $appDir)) {
                unset(self::$classMap[$className]);
            } elseif (@file_exists($file)) {
                require_once $file;
                $syncAlias($className);
                return true;
            } else {
                unset(self::$classMap[$className]); // File deleted or relocated, remove from map
            }
        }

        $file = self::resolveClass($className);
        if ($file && @file_exists($file)) {
            $real = realpath($file);
            if ($real) {
                $file = $real;
            }
            self::$classMap[$className] = $file;
            self::$mapChanged = true;
            require_once $file;
            $syncAlias($className);
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

        if ($className === 'SPP\\Core\\EventManager' || $className === 'SPP\\EventManager') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.sppevent.php';
            class_alias('SPP\\SPPEvent', $className);
            return true;
        }
        if ($className === 'SPP\\Event\\SubscriberInterface' || $className === 'SPP\\Core\\Interfaces\\SubscriberInterface') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'interfaces' . DIRECTORY_SEPARATOR . 'SubscriberInterface.php';
            return true;
        }
        if ($className === 'SPP\\Event\\Event' || $className === 'SPP\\Event') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.event.php';
            return true;
        }
        if ($className === 'SPP\\Event\\EventRecord' || $className === 'SPP\\EventRecord') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.eventrecord.php';
            return true;
        }
        if ($className === 'SPP\\Event\\EventBuilder' || $className === 'SPP\\EventBuilder') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.eventbuilder.php';
            return true;
        }
        if ($className === 'SPP\\Event\\CompoundTrigger' || $className === 'SPP\\CompoundTrigger') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.compoundtrigger.php';
            return true;
        }
        if ($className === 'SPP\\Exceptions\\EventAbortedException' || $className === 'SPP\\EventAbortedException') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'exceptions' . DIRECTORY_SEPARATOR . 'EventAbortedException.php';
            return true;
        }
        if ($className === 'SPP\\File') {
            require_once SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'class.file.php';
            return true;
        }
        if ($className === 'SPP\\CacheInterface' || $className === 'SPP\\MiddlewareInterface' || $className === 'SPP\\iModule' || $className === 'SPP\\Storage' || $className === 'SPP\\SmartStorage' || $className === 'SPP\\Infra') {
            $target = '\\SPP\\Core\\' . substr($className, 4);
            if (!class_exists('\\' . $className, false)) {
                class_alias($target, '\\' . $className);
            }
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

            // Check if a dedicated concrete exception class file exists first
            $pathParts = explode('\\', $className);
            $exceptionClass = end($pathParts);
            $candidatePaths = [
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'Polyglot' . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . $exceptionClass . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'exceptions' . DIRECTORY_SEPARATOR . $exceptionClass . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'Exceptions' . DIRECTORY_SEPARATOR . $exceptionClass . '.php',
            ];
            foreach ($candidatePaths as $cPath) {
                if (file_exists($cPath)) {
                    require_once $cPath;
                    return true;
                }
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
        if ($className === 'SPP\\Mesh') {
            $meshFile = SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'Mesh.php';
            if (file_exists($meshFile)) return $meshFile;
        }

        if (empty($path) || (count($path) >= 1 && $path[0] === 'SPP')) {
            // Resolve subdirectories under SPP\Core\... (e.g. SPP\Core\Apps\AppSupervisor)
            if (count($path) >= 2 && $path[0] === 'SPP' && $path[1] === 'Core') {
                $subparts = array_slice($path, 2);
                if (!empty($subparts)) {
                    $subPath = implode(DIRECTORY_SEPARATOR, $subparts);
                    $candidates = [
                        SPP_CORE_DIR . DIRECTORY_SEPARATOR . $subPath . DIRECTORY_SEPARATOR . $class . '.php',
                        SPP_CORE_DIR . DIRECTORY_SEPARATOR . strtolower($subPath) . DIRECTORY_SEPARATOR . $class . '.php',
                        SPP_CORE_DIR . DIRECTORY_SEPARATOR . strtolower($subPath) . DIRECTORY_SEPARATOR . 'class.' . strtolower($class) . '.php',
                    ];
                    foreach ($candidates as $cand) {
                        if (file_exists($cand)) return $cand;
                    }
                }
            }

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
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'router' . DIRECTORY_SEPARATOR . 'class.' . strtolower($class) . '.php',
                SPP_CORE_DIR . DIRECTORY_SEPARATOR . 'router' . DIRECTORY_SEPARATOR . $class . '.php',
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
        if (class_exists('\\SPP\\App', false) && class_exists('\\Symfony\\Component\\Yaml\\Yaml', false)) {
            $srcPath = (string)\SPP\App::getGlobalSettings("apps.{$appName}.src_path");
        }
        if (empty($srcPath)) {
            $srcDir = defined('SPP_APP_DIR') ? (SPP_APP_DIR . DIRECTORY_SEPARATOR . 'src') : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src');
            if (is_dir($srcDir . DIRECTORY_SEPARATOR . $appName)) {
                return $srcDir . DIRECTORY_SEPARATOR . $appName;
            }
            // Case-insensitive match on disk to preserve canonical casing
            if (is_dir($srcDir)) {
                foreach (scandir($srcDir) as $entry) {
                    if ($entry !== '.' && $entry !== '..' && strcasecmp($entry, $appName) === 0 && is_dir($srcDir . DIRECTORY_SEPARATOR . $entry)) {
                        return $srcDir . DIRECTORY_SEPARATOR . $entry;
                    }
                }
            }
            // Also check domains/*/src/<appName>
            $domainsDir = defined('SPP_APP_DIR') ? (SPP_APP_DIR . DIRECTORY_SEPARATOR . 'domains') : (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'domains');
            if (is_dir($domainsDir)) {
                foreach (scandir($domainsDir) as $dom) {
                    if ($dom === '.' || $dom === '..' || !is_dir($domainsDir . DIRECTORY_SEPARATOR . $dom)) continue;
                    $domSrcDir = $domainsDir . DIRECTORY_SEPARATOR . $dom . DIRECTORY_SEPARATOR . 'src';
                    if (!is_dir($domSrcDir)) continue;
                    $domAppDir = $domSrcDir . DIRECTORY_SEPARATOR . $appName;
                    if (is_dir($domAppDir)) return $domAppDir;
                    foreach (scandir($domSrcDir) ?: [] as $e) {
                        if ($e !== '.' && $e !== '..' && strcasecmp($e, $appName) === 0 && is_dir($domSrcDir . DIRECTORY_SEPARATOR . $e)) {
                            return $domSrcDir . DIRECTORY_SEPARATOR . $e;
                        }
                    }
                }
            }
            return $srcDir . DIRECTORY_SEPARATOR . $appName;
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
                if (!is_dir($modDir)) {
                    $optionalDir = SPP_MODULES_DIR . DIRECTORY_SEPARATOR . 'optional' . DIRECTORY_SEPARATOR . $mod;
                    if (is_dir($optionalDir)) {
                        $modDir = $optionalDir;
                    }
                }

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
        if (class_exists('\\SPP\\Registry', false) && \SPP\Registry::isRegistered('__modns')) {
            $nsMap = \SPP\Registry::get('__modns');
            foreach ($nsMap as $ns => $modPath) {
                if (strpos($className, $ns . '\\') === 0) {
                    $relativeClass = substr($className, strlen($ns) + 1);
                    $file = $modPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                    if (file_exists($file)) return $file;
                    $file = $modPath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
                    if (file_exists($file)) return $file;
                }
            }
        }

        if (strpos($className, 'SPP\\Admin\\Services\\') === 0) {
            $serviceFile = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . substr($className, strlen('SPP\\Admin\\Services\\')) . '.php';
            if (file_exists($serviceFile)) return $serviceFile;
        }

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
        $isAppPrefix = (strpos($className, 'App\\') === 0);
        $fullPath = explode('\\', $className);

        if (!$isAppPrefix) {
            $rootNs = strtolower($fullPath[0] ?? '');
            if (in_array($rootNs, ['symfony', 'composer', 'twig', 'eftec', 'league', 'psr'], true)) {
                return null;
            }
            // Check if root namespace is an existing app directory under src/
            $candidateApp = $rootNs;
            $candidateSrc = self::resolveAppSrcPath($candidateApp);
            if (is_dir($candidateSrc)) {
                // Prepend virtual 'App' segment so fullPath matches ['App', $fullPath[0], ...]
                array_unshift($fullPath, 'App');
            } else {
                return null;
            }
        }
        
        if (count($fullPath) >= 3) {
            $appName = strtolower($fullPath[1]);
            $baseSrc = self::resolveAppSrcPath($appName);

            if (count($fullPath) >= 4) {
                $type = strtolower($fullPath[2]);
                $name = strtolower($fullPath[3]);

                $file = '';
                if ($type === 'entities') {
                    $legacyFile = $baseSrc . DIRECTORY_SEPARATOR . 'entities' . DIRECTORY_SEPARATOR . 'entity.' . $name . '.php';
                    if (file_exists($legacyFile)) {
                        $file = $legacyFile;
                    } else {
                        // Support standard PSR-4 naming (e.g., Entities/Organization.php)
                        $psr4File = $baseSrc . DIRECTORY_SEPARATOR . 'Entities' . DIRECTORY_SEPARATOR . $fullPath[3] . '.php';
                        if (file_exists($psr4File)) {
                            $file = $psr4File;
                        } else {
                            $lowerFile = $baseSrc . DIRECTORY_SEPARATOR . 'entities' . DIRECTORY_SEPARATOR . $fullPath[3] . '.php';
                            if (file_exists($lowerFile)) {
                                $file = $lowerFile;
                            }
                        }
                    }
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

                // Dedicated check for package services ($baseSrc/packages/*/Services/{ServiceName}.php)
                if ($type === 'services') {
                    $packagesDir = $baseSrc . DIRECTORY_SEPARATOR . 'packages';
                    if (is_dir($packagesDir)) {
                        $serviceRel = implode(DIRECTORY_SEPARATOR, array_slice($fullPath, 3)) . '.php';
                        $subdirs = @scandir($packagesDir) ?: [];
                        foreach ($subdirs as $pkg) {
                            if ($pkg === '.' || $pkg === '..') continue;
                            $candidate = $packagesDir . DIRECTORY_SEPARATOR . $pkg . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . $serviceRel;
                            if (file_exists($candidate)) {
                                return $candidate;
                            }
                        }
                    }
                }

                // Dedicated check for package paths ($baseSrc/packages/{pkgName}/{SubPath}.php)
                if ($type === 'packages' && count($fullPath) >= 5) {
                    $pkgName = strtolower($fullPath[3]);
                    $subParts = array_slice($fullPath, 4);
                    $candidate = $baseSrc . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $pkgName . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $subParts) . '.php';
                    if (file_exists($candidate)) {
                        return $candidate;
                    }
                }

                // Fallback for case-insensitive / lowercase directories
                $remaining = array_slice($fullPath, 2);
                $lowerDirs = array_map('strtolower', array_slice($remaining, 0, -1));
                $lowerDirs[] = end($remaining);
                $lowerFile = $baseSrc . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $lowerDirs) . '.php';
                if (file_exists($lowerFile)) {
                    return $lowerFile;
                }
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
        $cleanClass = str_replace('_', '', $lowerClass);
        $searchFiles = [
            $modDir . DIRECTORY_SEPARATOR . $relPath . 'class.' . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . 'class.' . $cleanClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . 'int.' . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . 'trait.' . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . $lowerClass . '.php',
            $modDir . DIRECTORY_SEPARATOR . $relPath . $cleanClass . '.php',
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
        if (self::$mapChanged && self::$cacheFile && is_array(self::$classMap)) {
            $dir = dirname(self::$cacheFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $cleanMap = [];
            $appDir = defined('SPP_APP_DIR') ? str_replace('\\', '/', SPP_APP_DIR) : '';
            if ($appDir !== '') {
                $cleanMap['__root'] = $appDir;
            }
            foreach (self::$classMap as $cls => $fPath) {
                if (is_string($cls) && $cls !== '' && is_string($fPath) && $fPath !== '' && $cls !== '__root') {
                    $normPath = str_replace('\\', '/', $fPath);
                    if ($appDir !== '' && str_starts_with($normPath, $appDir . '/')) {
                        $cleanMap[$cls] = './' . substr($normPath, strlen($appDir) + 1);
                    } else {
                        $cleanMap[$cls] = $fPath;
                    }
                }
            }
            $content = "<?php\nreturn " . var_export($cleanMap, true) . ";\n";
            $tmpFile = self::$cacheFile . '.' . uniqid('cm_', true) . '.tmp';
            if (@file_put_contents($tmpFile, $content, LOCK_EX) !== false) {
                @rename($tmpFile, self::$cacheFile);
            }
            self::$mapChanged = false;
        }
    }
}
