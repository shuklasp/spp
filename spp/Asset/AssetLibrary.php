<?php
namespace SPP\Asset;

/**
 * AssetLibrary
 * 
 * Manages CSS/JS asset dependencies, aggregation, and caching.
 */
class AssetLibrary
{
    private static array $libraries = [];
    private static array $activeLibraries = [];

    /**
     * Register a library (equivalent to *.libraries.yml).
     */
    public static function register(string $name, array $definition): void
    {
        self::$libraries[$name] = $definition;
    }

    /**
     * Attach a library to the current request.
     */
    public static function attach(string $name): void
    {
        if (!isset(self::$activeLibraries[$name]) && isset(self::$libraries[$name])) {
            // Attach dependencies first
            if (!empty(self::$libraries[$name]['dependencies'])) {
                foreach (self::$libraries[$name]['dependencies'] as $dep) {
                    self::attach($dep);
                }
            }
            self::$activeLibraries[$name] = self::$libraries[$name];
        }
    }

    /**
     * Get all active CSS files, resolving aggregation if enabled.
     */
    public static function getCssUrls(bool $aggregate = false, string $cdnBase = ''): array
    {
        $files = [];
        foreach (self::$activeLibraries as $lib) {
            if (!empty($lib['css'])) {
                $files = array_merge($files, $lib['css']);
            }
        }

        if ($aggregate && !empty($files)) {
            return [self::aggregateFiles($files, 'css', $cdnBase)];
        }

        return array_map(fn($f) => $cdnBase . $f, $files);
    }

    /**
     * Get all active JS files, resolving aggregation if enabled.
     */
    public static function getJsUrls(bool $aggregate = false, string $cdnBase = ''): array
    {
        $files = [];
        foreach (self::$activeLibraries as $lib) {
            if (!empty($lib['js'])) {
                $files = array_merge($files, $lib['js']);
            }
        }

        if ($aggregate && !empty($files)) {
            return [self::aggregateFiles($files, 'js', $cdnBase)];
        }

        return array_map(fn($f) => $cdnBase . $f, $files);
    }

    /**
     * Concatenate files and save to public cache dir.
     */
    private static function aggregateFiles(array $files, string $type, string $cdnBase): string
    {
        $hash = md5(implode(',', $files));
        $cacheDir = (defined('SPP_APP_DIR') ? SPP_APP_DIR : '.') . '/public/var/assets';
        $publicPath = '/var/assets';
        
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $outFile = $cacheDir . "/bundle_{$hash}.{$type}";
        $outUrl = $publicPath . "/bundle_{$hash}.{$type}";

        if (!file_exists($outFile)) {
            $content = '';
            foreach ($files as $file) {
                // Determine absolute path
                $absPath = (defined('SPP_APP_DIR') ? SPP_APP_DIR : '.') . '/public' . $file;
                if (file_exists($absPath)) {
                    $content .= "/* Source: {$file} */\n";
                    $content .= file_get_contents($absPath) . "\n\n";
                }
            }
            file_put_contents($outFile, $content);
        }

        return $cdnBase . $outUrl;
    }
}
