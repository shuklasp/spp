<?php

namespace SPP\Core;

/**
 * Class MiddlewareKernel
 * Manages the registration and execution of the SPP Middleware Pipeline.
 */
class MiddlewareKernel
{
    protected static array $middleware = [];
    protected static bool $isInitialized = false;

    /**
     * Initialize the kernel by loading the middleware stack from the registry and config.
     */
    public static function boot()
    {
        if (self::$isInitialized) {
            return;
        }

        // 1. Load Core Middleware and Registry (Programmatic registration)
        $registered = \SPP\Registry::get('__middleware=>global') ?: [];
        self::$middleware = array_merge([
            \SPP\Core\Middleware\ApiAuthMiddleware::class
        ], (array) $registered);

        // 2. Load from Global Config
        $globalPath = SPP_ETC_DIR . SPP_DS . 'middleware.yml';
        if (file_exists($globalPath)) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($globalPath);
            self::$middleware = array_merge(self::$middleware, $config['global'] ?? []);
        }

        // 3. Load from App Config
        $context = \SPP\Scheduler::getContext();
        if ($context && $context !== 'default') {
            $appPath = \SPP\App::getApp()->getAppConfDir() . SPP_DS . 'middleware.yml';
            if (file_exists($appPath)) {
                $appConfig = \Symfony\Component\Yaml\Yaml::parseFile($appPath);
                self::$middleware = array_merge(self::$middleware, $appConfig['global'] ?? []);
            }
        }

        self::$isInitialized = true;
    }

    /**
     * Dynamically inject a global middleware into the pipeline.
     * Useful for modules registering middleware during boot.
     * 
     * @param string $middlewareClass The fully qualified class name of the middleware
     */
    public static function addGlobalMiddleware(string $middlewareClass): void
    {
        // Ensure initialized first so we don't overwrite it when boot() runs
        self::boot(); 
        if (!in_array($middlewareClass, self::$middleware, true)) {
            self::$middleware[] = $middlewareClass;
        }
    }

    /**
     * Executes the middleware pipeline for the current request.
     */
    public static function run(\Closure $destination)
    {
        self::boot();
        $context = \SPP\Scheduler::getContext() ?: 'default';
        \SPP\Scheduler::setContext($context);

        \SPP\SPPEvent::registerEvent('event_spp_kernel_boot');
        $params = new \SPP\EventParams();
        \SPP\SPPEvent::fireEvent('event_spp_kernel_boot', $params);

        return (new Pipeline())
            ->send($_REQUEST)
            ->through(self::$middleware)
            ->then($destination);
    }
}
