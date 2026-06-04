<?php

namespace SPPMod\SPPView;

/**
 * class AssetOrchestrator
 *
 * Handles bundling, minification, and cache-busting for CSS and JS assets.
 */
class AssetOrchestrator
{
    /**
     * Orchestrate a list of assets into a single bundled and versioned output.
     */
    public static function orchestrate(array $assets, string $type): string
    {
        if (empty($assets)) {
            return '';
        }

        $hash = md5(implode('|', $assets));
        $bundleName = "bundle_{$hash}.min.{$type}";
        $bundlePath = SPP_BASE_DIR . "/var/assets/{$bundleName}";

        if (!file_exists($bundlePath)) {
            self::generateBundle($assets, $bundlePath, $type);
        }

        $appBase = defined('APP_BASE_URI') ? rtrim(APP_BASE_URI, '/') : '';
        return "{$appBase}/var/assets/{$bundleName}";
    }

    /**
     * Generate the bundled and minified file.
     */
    private static function generateBundle(array $assets, string $outputPath, string $type): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $content = '';
        foreach ($assets as $asset) {
            $path = self::resolvePhysicalPath($asset);
            if ($path && file_exists($path)) {
                $content .= file_get_contents($path) . "\n";
            }
        }

        // Basic Minification (Strip comments and extra whitespace)
        $content = preg_replace('!/\*.*?\*/!s', '', $content);
        $content = preg_replace('/\n\s*\n/', "\n", $content);

        @file_put_contents($outputPath, $content);
    }

    /**
     * Resolve a URL to a physical filesystem path.
     */
    private static function resolvePhysicalPath(string $url): ?string
    {
        $appBase = defined('APP_BASE_URI') ? rtrim(APP_BASE_URI, '/') : '';
        if ($appBase !== '' && str_starts_with($url, $appBase)) {
            $url = substr($url, strlen($appBase));
        }

        $path = SPP_BASE_DIR . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $url), DIRECTORY_SEPARATOR);
        return file_exists($path) ? $path : null;
    }
}
