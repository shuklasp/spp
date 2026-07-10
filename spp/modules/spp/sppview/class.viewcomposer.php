<?php

namespace SPPMod\SPPView;

/**
 * Class ViewComposer
 * Allows registering callback functions, methods, or composer classes to be executed when specific view templates (or wildcard patterns) are rendered.
 */
class ViewComposer
{
    protected static array $composers = [];
    protected static array $matchCache = [];

    /**
     * Register a view composer for one or more views/wildcards.
     *
     * @param string|array $views View name(s) or wildcard pattern(s) (e.g., 'admin.*')
     * @param callable|string $callback Callback or class name with compose() method
     */
    public static function composer($views, $callback): void
    {
        foreach ((array)$views as $view) {
            self::$composers[$view][] = $callback;
        }
    }

    /**
     * Execute registered composers for a given view.
     *
     * @param string $view
     * @param array &$data
     */
    public static function compose(string $view, array &$data): void
    {
        foreach (self::$composers as $pattern => $callbacks) {
            if (self::matches($pattern, $view)) {
                foreach ($callbacks as $callback) {
                    if (is_string($callback) && class_exists($callback)) {
                        $instance = new $callback();
                        if (method_exists($instance, 'compose')) {
                            $instance->compose($data, $view);
                        }
                    } elseif (is_callable($callback)) {
                        call_user_func_array($callback, [&$data, $view]);
                    }
                }
            }
        }
    }

    /**
     * Check if a pattern matches a view name with runtime caching.
     */
    protected static function matches(string $pattern, string $view): bool
    {
        if ($pattern === $view) {
            return true;
        }
        $cacheKey = $pattern . '###' . $view;
        if (isset(self::$matchCache[$cacheKey])) {
            return self::$matchCache[$cacheKey];
        }
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
            $matched = (bool)preg_match($regex, $view);
            self::$matchCache[$cacheKey] = $matched;
            return $matched;
        }
        return false;
    }

    /**
     * Clear all registered composers and runtime cache.
     */
    public static function clearComposers(): void
    {
        self::$composers = [];
        self::$matchCache = [];
    }
}
