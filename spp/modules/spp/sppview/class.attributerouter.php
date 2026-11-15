<?php

declare(strict_types=1);

namespace SPPMod\SPPView;

use SPP\Attributes\Route;

/**
 * Class AttributeRouter
 *
 * Scans application controllers for #[Route] attributes, partitions routes by HTTP method,
 * supports both apps/ and src/ directory conventions, and persists compiled routes atomically.
 */
class AttributeRouter
{
    /**
     * Retrieve all compiled or discovered routes for an application.
     *
     * @param string $appName Application name
     * @param bool $forceRefresh Bypass cache and re-scan
     * @return array<string, array>
     */
    public static function getRoutes(string $appName, bool $forceRefresh = false): array
    {
        $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 4);
        $cacheDir = $baseDir . '/var/cache';
        $cacheFile = $cacheDir . '/routes_' . $appName . '.php';

        $env = getenv('APP_ENV') ?: (defined('SPP_ENV') ? SPP_ENV : 'production');
        $isLocal = in_array(strtolower((string)$env), ['local', 'dev', 'development', 'testing'], true);

        if (!$forceRefresh && file_exists($cacheFile) && !$isLocal) {
            $cached = include $cacheFile;
            if (is_array($cached)) {
                return $cached;
            }
        }

        $routes = [];
        $candidateDirs = [
            $baseDir . '/apps/' . $appName . '/controllers',
            $baseDir . '/apps/' . $appName . '/Controllers',
            $baseDir . '/src/' . $appName . '/Controllers',
            $baseDir . '/src/' . $appName . '/controllers',
        ];

        $discoveredClasses = [];

        foreach ($candidateDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $class = self::extractClassFromFile($file->getPathname());
                    if ($class && !in_array($class, $discoveredClasses, true)) {
                        require_once $file->getPathname();
                        $discoveredClasses[] = $class;
                    }
                }
            }
        }

        foreach ($discoveredClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            try {
                $reflector = new \ReflectionClass($class);
                foreach ($reflector->getMethods() as $method) {
                    $attributes = $method->getAttributes(Route::class);
                    foreach ($attributes as $attribute) {
                        $routeParams = $attribute->newInstance();
                        $path = ltrim((string)$routeParams->path, '/');
                        $httpMethod = strtoupper((string)($routeParams->method ?? 'GET'));

                        $routeRecord = [
                            'url' => '',
                            'path' => $path,
                            'controller' => $class . '@' . $method->getName(),
                            'class' => $class,
                            'action' => $method->getName(),
                            'auth' => $routeParams->auth ?? false,
                            'method' => $httpMethod,
                            'name' => property_exists($routeParams, 'name') ? $routeParams->name : null,
                        ];

                        // Partition by method:path to prevent HTTP verb collisions
                        $routes[$httpMethod . ':' . $path] = $routeRecord;

                        // Backward-compatible fallback entry if path not taken by same method
                        if (!isset($routes[$path])) {
                            $routes[$path] = $routeRecord;
                        }
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Atomic cache writing
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $export = "<?php\n// SPP Compiled Route Cache for [{$appName}]\n// Generated at " . date('Y-m-d H:i:s') . "\nreturn " . var_export($routes, true) . ";\n";
        $tmpFile = $cacheFile . '.tmp.' . uniqid('', true);
        if (@file_put_contents($tmpFile, $export) !== false) {
            @rename($tmpFile, $cacheFile);
        }

        return $routes;
    }

    /**
     * Match an incoming HTTP request against the application's attribute routes.
     *
     * @param string $httpMethod GET, POST, PUT, DELETE, etc.
     * @param string $path Relative path
     * @param string $appName Target application
     * @return array|null Route descriptor or null
     */
    public static function match(string $httpMethod, string $path, string $appName): ?array
    {
        $routes = self::getRoutes($appName);
        $cleanPath = ltrim($path, '/');
        $verb = strtoupper($httpMethod);

        // 1. Direct method:path match
        $key = $verb . ':' . $cleanPath;
        if (isset($routes[$key])) {
            return $routes[$key];
        }

        // 2. Fallback direct path match if method matches
        if (isset($routes[$cleanPath]) && strtoupper($routes[$cleanPath]['method'] ?? 'GET') === $verb) {
            return $routes[$cleanPath];
        }

        // 3. Dynamic parameter matching (e.g. users/{id})
        foreach ($routes as $routeKey => $route) {
            if (strtoupper($route['method'] ?? 'GET') !== $verb) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path'] ?? '');
            if ($pattern && preg_match('#^' . $pattern . '$#', $cleanPath, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $matchedRoute = $route;
                $matchedRoute['params'] = $params;
                return $matchedRoute;
            }
        }

        return null;
    }

    /**
     * Tokenizes a PHP file to extract its fully-qualified class name without execution.
     */
    private static function extractClassFromFile(string $filePath): ?string
    {
        $contents = @file_get_contents($filePath);
        if (!$contents) return null;

        $tokens = token_get_all($contents);
        $namespace = '';
        $class = '';

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $i += 2;
                $namespace = '';
                while (isset($tokens[$i]) && (is_array($tokens[$i]) || $tokens[$i] === '\\')) {
                    if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED])) {
                        $namespace .= $tokens[$i][1];
                    } elseif ($tokens[$i] === '\\') {
                        $namespace .= '\\';
                    }
                    $i++;
                }
            }

            if ($tokens[$i][0] === T_CLASS) {
                $i += 2;
                if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $class = $tokens[$i][1];
                    break;
                }
            }
        }

        return $class ? ($namespace ? $namespace . '\\' . $class : $class) : null;
    }
}
