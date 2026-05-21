<?php
namespace App\Lekhak\Themes;

use SPP\Theme\ThemeAdapterInterface;

/**
 * WordPressThemeAdapter
 *
 * Loads and renders templates from a WordPress theme directory structure.
 * Expects themes to follow the standard WP hierarchy:
 *   themes/<theme-name>/style.css
 *   themes/<theme-name>/index.php
 *   themes/<theme-name>/header.php
 *   themes/<theme-name>/footer.php
 *   themes/<theme-name>/page.php
 *   themes/<theme-name>/single.php
 *   etc.
 *
 * Does NOT bootstrap the full WordPress core — only loads the template files
 * and makes context variables available via extract().
 */
class WordPressThemeAdapter implements ThemeAdapterInterface
{
    /** @var string Absolute path to the active WP theme directory */
    private string $themePath;

    /** @var string Theme slug (directory name) */
    private string $themeSlug;

    public function __construct(string $themePath = '')
    {
        if ($themePath) {
            $this->themePath = rtrim($themePath, '/\\');
        } else {
            // Default: look under src/lekhak/themes/wp-content/themes/<active>
            $this->themePath = $this->resolveDefaultPath();
        }
        $this->themeSlug = basename($this->themePath);
    }

    /**
     * WordPress template hierarchy mapping.
     * Maps logical SPP names to the WP file convention.
     */
    private static array $hierarchy = [
        'page--home'    => ['front-page.php', 'home.php', 'index.php'],
        'page--single'  => ['single.php', 'singular.php', 'index.php'],
        'page--page'    => ['page.php', 'singular.php', 'index.php'],
        'page--archive' => ['archive.php', 'index.php'],
        'page--search'  => ['search.php', 'index.php'],
        'page--404'     => ['404.php', 'index.php'],
    ];

    public function loadTemplate(string $name): string
    {
        $file = $this->resolveTemplateFile($name);
        if (!$file || !file_exists($file)) {
            return "<!-- WordPressThemeAdapter: template '{$name}' not found in {$this->themeSlug} -->";
        }
        return file_get_contents($file);
    }

    public function render(string $template, array $context = []): string
    {
        $file = $this->resolveTemplateFile($template);
        if (!$file || !file_exists($file)) {
            return "<!-- WordPressThemeAdapter: template '{$template}' not found -->";
        }

        // Make context available as local variables (WP style)
        extract($context, EXTR_SKIP);

        // Provide WP-like helper functions within scope
        $get_header = function () use ($context) {
            $headerFile = $this->themePath . '/header.php';
            if (file_exists($headerFile)) {
                extract($context, EXTR_SKIP);
                include $headerFile;
            }
        };

        $get_footer = function () use ($context) {
            $footerFile = $this->themePath . '/footer.php';
            if (file_exists($footerFile)) {
                extract($context, EXTR_SKIP);
                include $footerFile;
            }
        };

        $get_sidebar = function (string $name = '') use ($context) {
            $sidebarFile = $name
                ? $this->themePath . "/sidebar-{$name}.php"
                : $this->themePath . '/sidebar.php';
            if (file_exists($sidebarFile)) {
                extract($context, EXTR_SKIP);
                include $sidebarFile;
            }
        };

        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Get asset URL for the active WP theme.
     */
    public function getStylesheetUri(): string
    {
        return $this->themePath . '/style.css';
    }

    public function getThemeSlug(): string
    {
        return $this->themeSlug;
    }

    // ── Internal ───────────────────────────────────────────────────────

    private function resolveTemplateFile(string $name): ?string
    {
        // 1. Check the predefined hierarchy
        $candidates = self::$hierarchy[$name] ?? null;
        if ($candidates) {
            foreach ($candidates as $file) {
                $path = $this->themePath . '/' . $file;
                if (file_exists($path)) return $path;
            }
        }

        // 2. Try direct file name  (e.g., 'page--about' => 'page-about.php')
        $wpName = str_replace('--', '-', $name) . '.php';
        $path = $this->themePath . '/' . $wpName;
        if (file_exists($path)) return $path;

        // 3. Fallback to index.php
        $fallback = $this->themePath . '/index.php';
        return file_exists($fallback) ? $fallback : null;
    }

    private function resolveDefaultPath(): string
    {
        $base = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3);
        $wpThemeBase = $base . '/src/lekhak/themes/wp-content/themes';

        // Read active theme from global-settings
        $activeTheme = 'twentytwentyfour'; // default
        if (class_exists('\\SPP\\SPPConfig')) {
            $configured = \SPP\SPPConfig::get('wp_theme');
            if ($configured) $activeTheme = $configured;
        }

        return $wpThemeBase . '/' . $activeTheme;
    }
}
