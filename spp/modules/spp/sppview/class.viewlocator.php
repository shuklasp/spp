<?php

namespace SPPMod\SPPView;

/**
 * Class ViewLocator
 * Service responsible for resolving view templates across custom registered paths and standard application directories.
 * Provides persistent disk caching to avoid redundant filesystem checks and strict sandboxing to prevent LFI attacks.
 */
class ViewLocator
{
    protected static array $customPaths = [];
    protected static array $pathCache = [];
    protected static bool $cacheLoaded = false;

    /**
     * Load the persistent view map cache from disk.
     */
    protected static function loadCache(): void
    {
        if (self::$cacheLoaded) {
            return;
        }
        self::$cacheLoaded = true;
        if (defined('SPP_APP_DIR')) {
            $cacheFile = SPP_APP_DIR . '/spp/etc/view_cache.php';
            if (file_exists($cacheFile)) {
                $cachedData = @include $cacheFile;
                if (is_array($cachedData)) {
                    self::$pathCache = array_merge($cachedData, self::$pathCache);
                }
            }
        }
    }

    /**
     * Save the current runtime view map cache to disk.
     */
    public static function saveCache(): void
    {
        if (defined('SPP_APP_DIR')) {
            $cacheDir = SPP_APP_DIR . '/spp/etc';
            if (!is_dir($cacheDir)) {
                @mkdir($cacheDir, 0777, true);
            }
            $cacheFile = $cacheDir . '/view_cache.php';
            $exported = var_export(self::$pathCache, true);
            @file_put_contents($cacheFile, "<?php\nreturn {$exported};\n");
        }
    }

    /**
     * Register a custom base path for discovering view templates.
     *
     * @param string $path
     */
    public static function addPath(string $path): void
    {
        $path = rtrim($path, '/\\');
        if (!in_array($path, self::$customPaths)) {
            self::$customPaths[] = $path;
        }
    }

    /**
     * Locate the absolute file path for a view template.
     *
     * @param string $view Name or path of the view template
     * @param string $app Name of the application context
     * @return string|null Absolute path to the view file or null if not found
     */
    public static function locate(string $view, string $app): ?string
    {
        self::loadCache();

        $cacheKey = $app . '.' . $view;
        if (isset(self::$pathCache[$cacheKey])) {
            if (file_exists(self::$pathCache[$cacheKey])) {
                return self::$pathCache[$cacheKey];
            } else {
                unset(self::$pathCache[$cacheKey]);
            }
        }

        if (file_exists($view)) {
            self::$pathCache[$cacheKey] = $view;
            self::saveCache();
            return $view;
        }

        if (defined('SPP_APP_DIR') && file_exists(SPP_APP_DIR . '/' . $view)) {
            if (is_file(SPP_APP_DIR . '/' . $view)) {
                self::$pathCache[$cacheKey] = SPP_APP_DIR . '/' . $view;
                self::saveCache();
                return SPP_APP_DIR . '/' . $view;
            }
        }

        $hasExtension = preg_match('/\.(php|html|js|blade\.php)$/i', $view);
        $extensions = $hasExtension ? [''] : ['.blade.php', '.php', '.html', '.js'];

        $viewFiles = [];
        $theme = class_exists('\\SPP\\SPPConfig') ? (\SPP\SPPConfig::get('app:theme') ?? \SPP\SPPConfig::get('app.theme') ?? \SPP\SPPConfig::get('theme') ?? 'default') : 'default';

        foreach ($extensions as $ext) {
            foreach (self::$customPaths as $customPath) {
                $viewFiles[] = $customPath . "/{$app}/views/{$view}{$ext}";
                $viewFiles[] = $customPath . "/{$app}/comp/{$view}{$ext}";
                $viewFiles[] = $customPath . "/{$app}/pages/{$view}{$ext}";
                $viewFiles[] = $customPath . "/{$app}/partials/{$view}{$ext}";
                $viewFiles[] = $customPath . "/{$app}/streams/{$view}{$ext}";
                $viewFiles[] = $customPath . "/{$view}{$ext}";
            }

            if (defined('SPP_APP_DIR')) {
                $viewFiles = array_merge($viewFiles, [
                    SPP_APP_DIR . "/{$view}{$ext}",
                    SPP_APP_DIR . "/src/{$app}/pages/" . basename($view) . "{$ext}",
                    SPP_APP_DIR . "/src/{$app}/views/" . basename($view) . "{$ext}",
                    SPP_APP_DIR . "/src/{$app}/partials/" . basename($view) . "{$ext}",
                    SPP_APP_DIR . "/src/{$app}/streams/" . basename($view) . "{$ext}",
                    SPP_APP_DIR . "/themes/{$theme}/{$app}/views/{$view}{$ext}",
                    SPP_APP_DIR . "/themes/{$theme}/{$app}/partials/{$view}{$ext}",
                    SPP_APP_DIR . "/themes/{$theme}/{$app}/streams/{$view}{$ext}",
                    SPP_APP_DIR . "/themes/{$theme}/views/{$view}{$ext}",
                    SPP_APP_DIR . "/themes/{$theme}/partials/{$view}{$ext}",
                    SPP_APP_DIR . "/themes/{$theme}/streams/{$view}{$ext}",
                    SPP_APP_DIR . "/resources/{$app}/views/{$view}{$ext}",
                    SPP_APP_DIR . "/resources/{$app}/partials/{$view}{$ext}",
                    SPP_APP_DIR . "/resources/{$app}/streams/{$view}{$ext}",
                    SPP_APP_DIR . "/spp/etc/apps/{$app}/views/{$view}{$ext}",
                    SPP_APP_DIR . "/src/{$app}/views/{$view}{$ext}",
                    SPP_APP_DIR . "/src/{$app}/comp/{$view}{$ext}",
                    SPP_APP_DIR . "/src/{$app}/pages/{$view}{$ext}",
                    SPP_APP_DIR . "/src/{$app}/partials/{$view}{$ext}",
                    SPP_APP_DIR . "/src/{$app}/streams/{$view}{$ext}",
                    SPP_APP_DIR . "/resources/views/{$view}{$ext}",
                    SPP_APP_DIR . "/resources/partials/{$view}{$ext}",
                    SPP_APP_DIR . "/resources/streams/{$view}{$ext}",
                ]);
            }
        }

        foreach ($viewFiles as $file) {
            if (file_exists($file)) {
                // Strict Path Sandboxing (LFI Protection)
                if (defined('SPP_APP_DIR')) {
                    $realPath = realpath($file);
                    $baseDir = realpath(SPP_APP_DIR);
                    if ($realPath !== false && $baseDir !== false) {
                        if (strpos($realPath, $baseDir) !== 0) {
                            continue; // Prevent local file inclusion / directory traversal outside SPP_APP_DIR
                        }
                    }
                }

                self::$pathCache[$cacheKey] = $file;
                self::saveCache();
                return $file;
            }
        }

        return null;
    }

    /**
     * Clear the runtime view path cache and purge the persistent cache file.
     */
    public static function clearCache(): void
    {
        self::$pathCache = [];
        if (defined('SPP_APP_DIR')) {
            $cacheFile = SPP_APP_DIR . '/spp/etc/view_cache.php';
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
        }
    }
}
