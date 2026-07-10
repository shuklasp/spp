<?php

namespace SPP\Core\Router;

use SPP\Scheduler;
use SPP\Core\SPPException;

class Router
{
    private static array $routes = [];
    private static string $currentGroupPrefix = '';
    private static array $currentGroupMiddleware = [];

    public static function getRoutes(): array
    {
        return self::$routes;
    }

    public static function get(string $uri, string|callable $action, array $middleware = []): void
    {
        self::addRoute('GET', $uri, $action, $middleware);
    }

    public static function post(string $uri, string|callable $action, array $middleware = []): void
    {
        self::addRoute('POST', $uri, $action, $middleware);
    }

    public static function put(string $uri, string|callable $action, array $middleware = []): void
    {
        self::addRoute('PUT', $uri, $action, $middleware);
    }

    public static function delete(string $uri, string|callable $action, array $middleware = []): void
    {
        self::addRoute('DELETE', $uri, $action, $middleware);
    }

    public static function group(array $attributes, callable $callback): void
    {
        $previousGroupPrefix = self::$currentGroupPrefix;
        $previousGroupMiddleware = self::$currentGroupMiddleware;

        if (isset($attributes['prefix'])) {
            self::$currentGroupPrefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            self::$currentGroupMiddleware = array_merge(self::$currentGroupMiddleware, (array) $attributes['middleware']);
        }

        call_user_func($callback);

        self::$currentGroupPrefix = $previousGroupPrefix;
        self::$currentGroupMiddleware = $previousGroupMiddleware;
    }

    private static function addRoute(string $method, string $uri, string|callable $action, array $middleware = []): void
    {
        $uri = self::$currentGroupPrefix . '/' . trim($uri, '/');
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        $allMiddleware = array_merge(self::$currentGroupMiddleware, $middleware);

        self::$routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => $allMiddleware
        ];
    }

    public static function dispatch(string $method, string $uri)
    {
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        foreach (self::$routes as $route) {
            if ($route['method'] === $method) {
                // Simple regex match for params
                $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[a-zA-Z0-9_\-]+)', $route['uri']);
                $pattern = '#^' . $pattern . '$#';

                if (preg_match($pattern, $uri, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    
                    // In a real framework, we would run middleware here
                    // For now, execute action
                    if (is_callable($route['action'])) {
                        return call_user_func_array($route['action'], $params);
                    }
                    
                    if (is_string($route['action']) && str_contains($route['action'], '@')) {
                        [$controller, $methodName] = explode('@', $route['action']);
                        if (class_exists($controller)) {
                            $instance = new $controller();
                            if (method_exists($instance, $methodName)) {
                                return call_user_func_array([$instance, $methodName], $params);
                            }
                        }
                    }
                    throw new SPPException("Route action could not be resolved.");
                }
            }
        }
        
        throw new SPPException("Route not found.", 404);
    }
}
