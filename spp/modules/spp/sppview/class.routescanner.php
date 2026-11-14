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

        $scannedFiles = [];

        foreach ($regex as $file) {
            $filePath = $file[0];
            $realPath = realpath($filePath) ?: $filePath;
            if (isset($scannedFiles[$realPath])) {
                continue;
            }
            $scannedFiles[$realPath] = true;

            $content = file_get_contents($realPath);
            if ($content === false) {
                continue;
            }
            
            // Extract namespace and class name via PHP tokenizer to eliminate false positives in docblocks/comments
            $parsed = self::extractClassAndNamespace($content);
            $ns = $parsed['namespace'];
            $class = $parsed['class'];
            
            if ($class) {
                $fullClass = $ns ? $ns . '\\' . $class : $class;
                
                // Only scan if it starts with the correct namespace prefix if provided
                if ($namespacePrefix && !str_starts_with($fullClass, $namespacePrefix)) {
                    continue;
                }

                // Ensure the class is loaded - avoid re-requiring if already declared
                if (!class_exists($fullClass, false)) {
                    if (!class_exists($fullClass, true)) {
                        try {
                            require_once $realPath;
                        } catch (\Throwable $e) {
                            continue;
                        }
                    }
                }

                if (class_exists($fullClass, false)) {
                    $routes = array_merge($routes, self::scanClass($fullClass));
                }
            }
        }

        return $routes;
    }

    /**
     * Scans a single class for #[Route], #[Resource] attributes, or ResourceController inheritance.
     */
    public static function scanClass(string $fullClass): array
    {
        $routes = [];
        if (!class_exists($fullClass)) {
            return $routes;
        }

        $reflection = new ReflectionClass($fullClass);
        $class = $reflection->getShortName();

        // Class-level route prefixes, middleware, and where constraints
        $prefix = '';
        $classMiddlewares = [];
        $classWheres = [];

        $classAttributes = $reflection->getAttributes(Route::class);
        if (!empty($classAttributes)) {
            $routeAttr = $classAttributes[0]->newInstance();
            $prefix = rtrim($routeAttr->path, '/');
            if (!empty($routeAttr->where)) {
                $classWheres = array_merge($classWheres, $routeAttr->where);
            }
        }

        $classMiddlewareAttrs = $reflection->getAttributes(\SPPMod\SPPView\Attributes\Middleware::class);
        foreach ($classMiddlewareAttrs as $mAttr) {
            $classMiddlewares[] = $mAttr->newInstance()->class;
        }

        // Scan class-level constraint attributes if available
        $whereAttrClasses = [
            '\\SPPMod\\SPPView\\Attributes\\Where',
            '\\SPPMod\\SPPView\\Attributes\\WhereNumber',
            '\\SPPMod\\SPPView\\Attributes\\WhereUuid',
            '\\SPPMod\\SPPView\\Attributes\\WhereAlpha',
            '\\SPPMod\\SPPView\\Attributes\\WhereAlphaNumeric'
        ];
        foreach ($whereAttrClasses as $wClass) {
            if (class_exists($wClass)) {
                foreach ($reflection->getAttributes($wClass) as $wAttr) {
                    $inst = $wAttr->newInstance();
                    if (isset($inst->constraints) && is_array($inst->constraints)) {
                        $classWheres = array_merge($classWheres, $inst->constraints);
                    }
                }
            }
        }

        // Check for Resource Attribute or ResourceController subclass
        $isResourceController = $reflection->isSubclassOf('\\SPP\\Core\\ResourceController');
        $resourceAttributes = class_exists('\\SPPMod\\SPPView\\Attributes\\Resource')
            ? $reflection->getAttributes(\SPPMod\SPPView\Attributes\Resource::class)
            : [];

        if (!empty($resourceAttributes) || $isResourceController) {
            $resPath = '';
            $resMiddleware = [];
            if (!empty($resourceAttributes)) {
                $resInstance = $resourceAttributes[0]->newInstance();
                $resPath = $resInstance->path;
                $resMiddleware = $resInstance->middleware;
            }
            if (empty($resPath)) {
                $defaults = $reflection->getDefaultProperties();
                if (!empty($defaults['resourceName'])) {
                    $resPath = $defaults['resourceName'];
                }
            }
            if (empty($resPath)) {
                $resPath = $prefix ? ltrim($prefix, '/') : self::deriveResourcePath($class);
            }

            $allResourceMiddlewares = array_merge($classMiddlewares, $resMiddleware);

            // Synthesize convention REST routes
            $lead = '/' . ltrim($resPath, '/');
            $conventions = [
                $lead => ['method' => ['GET'], 'action' => 'index'],
                $lead . '/create' => ['method' => ['GET'], 'action' => 'create'],
                $lead . '/store' => ['method' => ['POST'], 'action' => 'store'],
                $lead . '/{id}' => ['method' => ['GET'], 'action' => 'show'],
                $lead . '/{id}/edit' => ['method' => ['GET'], 'action' => 'edit'],
                $lead . '/{id}/update' => ['method' => ['POST', 'PUT', 'PATCH'], 'action' => 'update'],
                $lead . '/{id}/delete' => ['method' => ['POST', 'DELETE'], 'action' => 'destroy'],
                $lead . '/{id}/transition/{transition}' => ['method' => ['POST', 'GET'], 'action' => 'applyTransitionAction'],
                $lead . '/{id}/transition' => ['method' => ['POST'], 'action' => 'applyTransitionAction']
            ];

            foreach ($conventions as $cPath => $cDef) {
                if ($reflection->hasMethod($cDef['action'])) {
                    $routes[$cPath] = [
                        'path' => $cPath,
                        'controller' => $fullClass . '@' . $cDef['action'],
                        'method' => $cDef['method'],
                        'name' => trim($resPath, '/') . '.' . $cDef['action'],
                        'middleware' => $allResourceMiddlewares,
                        'where' => $classWheres
                    ];
                }
            }
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodMiddlewares = [];
            $methodMiddlewareAttrs = $method->getAttributes(\SPPMod\SPPView\Attributes\Middleware::class);
            foreach ($methodMiddlewareAttrs as $mAttr) {
                $methodMiddlewares[] = $mAttr->newInstance()->class;
            }

            $methodWheres = $classWheres;
            foreach ($whereAttrClasses as $wClass) {
                if (class_exists($wClass)) {
                    foreach ($method->getAttributes($wClass) as $wAttr) {
                        $inst = $wAttr->newInstance();
                        if (isset($inst->constraints) && is_array($inst->constraints)) {
                            $methodWheres = array_merge($methodWheres, $inst->constraints);
                        }
                    }
                }
            }

            $attributes = $method->getAttributes(Route::class);
            foreach ($attributes as $attribute) {
                $routeAttr = $attribute->newInstance();
                $path = '/' . trim($prefix . '/' . ltrim($routeAttr->path, '/'), '/');

                $mergedMiddlewares = array_merge($classMiddlewares, $methodMiddlewares, $routeAttr->middleware);
                $mergedWheres = array_merge($methodWheres, $routeAttr->where ?? []);

                $routes[$path] = [
                    'path' => $path,
                    'controller' => $fullClass . '@' . $method->getName(),
                    'method' => explode('|', $routeAttr->method),
                    'name' => $routeAttr->name,
                    'middleware' => $mergedMiddlewares,
                    'where' => $mergedWheres
                ];
            }
        }

        return $routes;
    }

    /**
     * Derives a pluralized kebab-case resource path from a controller class name.
     * e.g. StudentController -> students, CourseController -> courses, UserProfileController -> user-profiles
     */
    public static function deriveResourcePath(string $class): string
    {
        $base = preg_replace('/Controller$/i', '', $class);
        $kebab = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $base));
        if (str_ends_with($kebab, 'y') && !str_ends_with($kebab, 'ay') && !str_ends_with($kebab, 'ey') && !str_ends_with($kebab, 'oy')) {
            return substr($kebab, 0, -1) . 'ies';
        }
        if (!str_ends_with($kebab, 's')) {
            return $kebab . 's';
        }
        return $kebab;
    }

    public static function extractClassAndNamespace(string $content): array
    {
        $tokens = token_get_all($content);
        $namespace = '';
        $class = '';

        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $i++;
                while (isset($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
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
                $i++;
                while (isset($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if (isset($tokens[$i]) && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $class = $tokens[$i][1];
                    break;
                }
            }
        }

        return ['namespace' => $namespace, 'class' => $class];
    }

    public static function extractNamespace(string $content): string
    {
        return self::extractClassAndNamespace($content)['namespace'];
    }

    public static function extractClass(string $content): string
    {
        return self::extractClassAndNamespace($content)['class'];
    }
}
