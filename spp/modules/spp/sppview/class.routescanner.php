<?php

namespace SPPMod\SPPView;

use ReflectionClass;
use ReflectionMethod;
use SPPMod\SPPView\Attributes\Route;

class RouteScanner
{
    /**
     * Scans a directory for classes and extracts Route attributes.
     * Returns an array of route definitions compatible with SPPRouter.
     */
    public static function scan(string $directory, string $namespacePrefix = ''): array
    {
        $routes = [];
        if (!is_dir($directory)) {
            return $routes;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file) {
            $filePath = $file[0];
            $content = file_get_contents($filePath);
            
            // Extract namespace and class name without requiring autoloader to load everything first
            $ns = self::extractNamespace($content);
            $class = self::extractClass($content);
            
            if ($class) {
                $fullClass = $ns ? $ns . '\\' . $class : $class;
                
                // Only scan if it starts with the correct namespace prefix if provided
                if ($namespacePrefix && !str_starts_with($fullClass, $namespacePrefix)) {
                    continue;
                }

                // Ensure the class is loaded
                if (!class_exists($fullClass)) {
                    require_once $filePath;
                }

                if (class_exists($fullClass)) {
                    $reflection = new ReflectionClass($fullClass);
                    
                    // Class-level route prefixes and middleware
                    $prefix = '';
                    $classMiddlewares = [];
                    $classAttributes = $reflection->getAttributes(Route::class);
                    if (!empty($classAttributes)) {
                        $routeAttr = $classAttributes[0]->newInstance();
                        $prefix = rtrim($routeAttr->path, '/');
                    }

                    $classMiddlewareAttrs = $reflection->getAttributes(\SPPMod\SPPView\Attributes\Middleware::class);
                    foreach ($classMiddlewareAttrs as $mAttr) {
                        $classMiddlewares[] = $mAttr->newInstance()->class;
                    }

                    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                        $methodMiddlewares = [];
                        $methodMiddlewareAttrs = $method->getAttributes(\SPPMod\SPPView\Attributes\Middleware::class);
                        foreach ($methodMiddlewareAttrs as $mAttr) {
                            $methodMiddlewares[] = $mAttr->newInstance()->class;
                        }
                        
                        $attributes = $method->getAttributes(Route::class);
                        foreach ($attributes as $attribute) {
                            $routeAttr = $attribute->newInstance();
                            $path = ltrim($prefix . '/' . ltrim($routeAttr->path, '/'), '/');
                            
                            $mergedMiddlewares = array_merge($classMiddlewares, $methodMiddlewares, $routeAttr->middleware);
                            
                            $routes[$path] = [
                                'controller' => $fullClass . '@' . $method->getName(),
                                'method' => explode('|', $routeAttr->method),
                                'name' => $routeAttr->name,
                                'middleware' => $mergedMiddlewares
                            ];
                        }
                    }
                }
            }
        }

        return $routes;
    }

    private static function extractNamespace(string $content): string
    {
        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+);/m', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private static function extractClass(string $content): string
    {
        if (preg_match('/class\s+([a-zA-Z0-9_]+)/m', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
