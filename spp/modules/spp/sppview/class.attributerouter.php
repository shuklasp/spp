<?php
namespace SPPMod\SPPView;

use SPP\Attributes\Route;

class AttributeRouter {
    public static function getRoutes(string $appName): array {
        $cacheFile = SPP_BASE_DIR . '/var/cache/routes_' . $appName . '.php';
        
        if (file_exists($cacheFile) && getenv('APP_ENV') !== 'local') {
            return include $cacheFile;
        }

        $routes = [];
        $controllerDir = SPP_APP_DIR . '/apps/' . $appName . '/controllers';
        
        if (!is_dir($controllerDir)) {
            return [];
        }

        // Basic inclusion to make classes available to get_declared_classes()
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDir));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
        
        $classes = get_declared_classes();
        foreach ($classes as $class) {
            if (str_contains(strtolower($class), strtolower("App\\$appName\\Controllers\\")) || str_contains(strtolower($class), 'controller')) {
                try {
                    $reflector = new \ReflectionClass($class);
                    foreach ($reflector->getMethods() as $method) {
                        $attributes = $method->getAttributes(Route::class);
                        foreach ($attributes as $attribute) {
                            $routeParams = $attribute->newInstance();
                            $path = ltrim($routeParams->path, '/');
                            $routes[$path] = [
                                'url' => '', // Attributes usually mean we don't render an HTML file automatically
                                'controller' => $class . '@' . $method->getName(),
                                'auth' => $routeParams->auth,
                                'method' => $routeParams->method
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0777, true);
        }
        file_put_contents($cacheFile, '<?php return ' . var_export($routes, true) . ';');
        return $routes;
    }
}
