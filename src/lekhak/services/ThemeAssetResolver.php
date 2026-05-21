<?php
namespace App\Lekhak\Services;

use SPP\Theme\ThemeAdapterInterface;
use App\Lekhak\Themes\WordPressThemeAdapter;
use App\Lekhak\Themes\JoomlaThemeAdapter;
use App\Lekhak\Themes\NativeThemeAdapter;

/**
 * ThemeAssetResolver
 *
 * Resolves logical asset names (e.g., 'style', 'script') to concrete
 * URLs or paths depending on which theme adapter is active.
 *
 * Also provides a factory method for creating the correct theme adapter
 * based on global-settings.yml configuration.
 */
class ThemeAssetResolver
{
    /** @var ThemeAdapterInterface|null Singleton */
    private static ?ThemeAdapterInterface $adapter = null;

    /**
     * Create and return the active theme adapter based on configuration.
     *
     * Reads `theme_adapter` from global-settings.yml:
     *   'wp'     → WordPressThemeAdapter
     *   'joomla' → JoomlaThemeAdapter
     *   'native' → NativeThemeAdapter (default)
     */
    public static function getAdapter(): ThemeAdapterInterface
    {
        if (self::$adapter !== null) {
            return self::$adapter;
        }

        $type = 'native';
        if (class_exists('\\SPP\\SPPConfig')) {
            $configured = \SPP\SPPConfig::get('theme_adapter');
            if ($configured) $type = strtolower($configured);
        }

        self::$adapter = match ($type) {
            'wp', 'wordpress' => new WordPressThemeAdapter(),
            'joomla'          => new JoomlaThemeAdapter(),
            default           => new NativeThemeAdapter(),
        };

        return self::$adapter;
    }

    /**
     * Override the active adapter (useful for testing or CLI commands).
     */
    public static function setAdapter(ThemeAdapterInterface $adapter): void
    {
        self::$adapter = $adapter;
    }

    /**
     * Resolve a stylesheet path for the active theme.
     */
    public static function getStylesheet(): string
    {
        $adapter = self::getAdapter();

        if ($adapter instanceof WordPressThemeAdapter) {
            return $adapter->getStylesheetUri();
        }

        if ($adapter instanceof JoomlaThemeAdapter) {
            return $adapter->getStylesheetPath();
        }

        if ($adapter instanceof NativeThemeAdapter) {
            return $adapter->getAssetsPath() . '/css/style.css';
        }

        return '';
    }

    /**
     * Resolve a generic asset path for the active theme.
     *
     * @param string $relativePath  e.g. 'js/app.js', 'images/logo.png'
     * @return string Absolute path to the asset
     */
    public static function resolveAsset(string $relativePath): string
    {
        $adapter = self::getAdapter();

        if ($adapter instanceof NativeThemeAdapter) {
            return $adapter->getAssetsPath() . '/' . ltrim($relativePath, '/');
        }

        $base = '';
        if ($adapter instanceof WordPressThemeAdapter) {
            $base = dirname(self::getStylesheet()); // theme root
        } elseif ($adapter instanceof JoomlaThemeAdapter) {
            $css = self::getStylesheet();
            $base = $css ? dirname(dirname($css)) : ''; // go up from css/
        }

        $resolved = $base ? $base . '/' . ltrim($relativePath, '/') : $relativePath;

        // Apply CDN rewrite if configured
        if (class_exists('\\SPP\\SPPConfig')) {
            $cdnBase = \SPP\SPPConfig::get('cdn_base_url');
            if ($cdnBase && str_starts_with($resolved, '/')) {
                return rtrim($cdnBase, '/') . $resolved;
            }
        }

        return $resolved;
    }

    /**
     * Get the type of the currently active adapter.
     *
     * @return string 'wordpress', 'joomla', or 'native'
     */
    public static function getAdapterType(): string
    {
        $adapter = self::getAdapter();
        return match (true) {
            $adapter instanceof WordPressThemeAdapter => 'wordpress',
            $adapter instanceof JoomlaThemeAdapter    => 'joomla',
            default                                   => 'native',
        };
    }
}
