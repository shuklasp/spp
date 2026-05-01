<?php
namespace SPPMod\Lekhak\Drivers;

use SPPMod\Lekhak\Core\Renderer;

/**
 * Class WPSimDriver
 * Simulates WordPress Global API and rendering.
 */
class WPSimDriver
{
    protected static array $context = [];

    public static function register(Renderer $renderer): void
    {
        $renderer->registerDriver('php', function($content, $data) {
            // If it looks like a WP template, boot the shim
            if (strpos($content, 'the_title(') !== false || strpos($content, 'have_posts(') !== false) {
                return self::renderWp($content, $data);
            }
            return null; // Fallback to standard PHP
        });
    }

    public static function renderWp(string $content, array $data): string
    {
        self::$context = $data;
        
        // Define global WP functions if not exists
        if (!function_exists('the_title')) {
            require_once(__DIR__ . '/wp-shim.php');
        }

        extract($data);
        ob_start();
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'lekhak_wp_');
        file_put_contents($tmpFile, $content);
        
        try {
            include $tmpFile;
        } finally {
            unlink($tmpFile);
        }

        return ob_get_clean();
    }

    public static function getContext(string $key = null)
    {
        if ($key === null) return self::$context;
        return self::$context[$key] ?? null;
    }
}
