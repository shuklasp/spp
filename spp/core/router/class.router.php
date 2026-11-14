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

    public static function getRoute(int $index): ?array
    {
        return self::$routes[$index] ?? null;
    }

    public static function updateRoute(int $index, array $updates): void
    {
        if (isset(self::$routes[$index])) {
            self::$routes[$index] = array_merge(self::$routes[$index], $updates);
        }
    }

    public static function clearRoutes(): void
    {
        self::$routes = [];
        self::$currentGroupPrefix = '';
        self::$currentGroupMiddleware = [];
    }

    public static function get(string $uri, string|callable $action, array $middleware = []): FluentRoute
    {
        return self::addRoute('GET', $uri, $action, $middleware);
    }

    public static function post(string $uri, string|callable $action, array $middleware = []): FluentRoute
    {
        return self::addRoute('POST', $uri, $action, $middleware);
    }

    public static function put(string $uri, string|callable $action, array $middleware = []): FluentRoute
    {
        return self::addRoute('PUT', $uri, $action, $middleware);
    }

    public static function patch(string $uri, string|callable $action, array $middleware = []): FluentRoute
    {
        return self::addRoute('PATCH', $uri, $action, $middleware);
    }

    public static function delete(string $uri, string|callable $action, array $middleware = []): FluentRoute
    {
        return self::addRoute('DELETE', $uri, $action, $middleware);
    }

    public static function any(string $uri, string|callable $action, array $middleware = []): FluentRoute
    {
        return self::addRoute('ANY', $uri, $action, $middleware);
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

    private static function addRoute(string $method, string $uri, string|callable $action, array $middleware = []): FluentRoute
    {
        $uri = self::$currentGroupPrefix . '/' . trim($uri, '/');
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        $allMiddleware = array_merge(self::$currentGroupMiddleware, $middleware);

        $index = count(self::$routes);
        self::$routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'action' => $action,
            'middleware' => $allMiddleware,
            'where' => []
        ];

        if (!class_exists('\\SPP\\Core\\Router\\FluentRoute')) {
            require_once __DIR__ . '/FluentRoute.php';
        }

        return new FluentRoute($index);
    }

    public static function dispatch(string $method, string $uri)
    {
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        $method = strtoupper($method);

        foreach (self::$routes as $route) {
            if ($route['method'] === $method || $route['method'] === 'ANY') {
                $wheres = $route['where'] ?? [];
                // Compile regex with parameter constraints if provided
                $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::[^\}]+)?\}/', function ($m) use ($wheres) {
                    $name = $m[1];
                    $regex = $wheres[$name] ?? '[a-zA-Z0-9_\-]+';
                    return '(?P<' . $name . '>' . $regex . ')';
                }, $route['uri']);
                $pattern = '#^' . $pattern . '$#';

                if (preg_match($pattern, $uri, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    // Execute route-level middleware stack
                    if (!empty($route['middleware']) && is_array($route['middleware'])) {
                        foreach ($route['middleware'] as $mw) {
                            if (is_string($mw) && class_exists($mw)) {
                                $mwInstance = new $mw();
                                if (method_exists($mwInstance, 'handle')) {
                                    $res = $mwInstance->handle();
                                    if ($res !== null && $res !== true) {
                                        return $res;
                                    }
                                }
                            }
                        }
                    }

                    // Execute action
                    if (is_callable($route['action'])) {
                        return call_user_func_array($route['action'], $params);
                    }

                    if (is_string($route['action']) && str_contains($route['action'], '@')) {
                        [$controller, $methodName] = explode('@', $route['action']);
                        if (class_exists($controller)) {
                            $instance = null;
                            if (class_exists('\\SPP\\Core\\Container')) {
                                try {
                                    $instance = \SPP\Core\Container::getInstance()->get($controller);
                                } catch (\Throwable $e) {
                                    $instance = new $controller();
                                }
                            } else {
                                $instance = new $controller();
                            }

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
