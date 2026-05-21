<?php
namespace App\Lekhak\Themes;

use SPP\Theme\ThemeAdapterInterface;

/**
 * NativeThemeAdapter
 *
 * Loads and renders Lekhak's own Blade-style / Twig templates.
 * This is the default adapter when no WordPress or Joomla theme is configured.
 *
 * Template resolution order:
 *   1. src/lekhak/themes/<active>/views/<name>.blade.php
 *   2. src/lekhak/themes/<active>/views/<name>.twig
 *   3. src/lekhak/themes/<active>/views/<name>.php
 *   4. resources/PremiumApp/views/<name>.blade.php  (fallback)
 */
class NativeThemeAdapter implements ThemeAdapterInterface
{
    /** @var string Active theme directory */
    private string $themePath;

    /** @var string Theme name */
    private string $themeName;

    public function __construct(string $themePath = '')
    {
        if ($themePath) {
            $this->themePath = rtrim($themePath, '/\\');
        } else {
            $this->themePath = $this->resolveDefaultPath();
        }
        $this->themeName = basename($this->themePath);
    }

    public function loadTemplate(string $name): string
    {
        $file = $this->resolveTemplateFile($name);
        if (!$file) {
            return "<!-- NativeThemeAdapter: template '{$name}' not found in {$this->themeName} -->";
        }
        return file_get_contents($file);
    }

    public function render(string $template, array $context = []): string
    {
        $file = $this->resolveTemplateFile($template);
        if (!$file) {
            return "<!-- NativeThemeAdapter: template '{$template}' not found -->";
        }

        // Make context available
        extract($context, EXTR_SKIP);

        ob_start();

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if ($ext === 'twig' && class_exists('\\Twig\\Environment')) {
            // Render via Twig if available
            echo $this->renderTwig($file, $context);
        } else {
            // Blade-style or plain PHP include
            include $file;
        }

        return ob_get_clean();
    }

    public function getThemeName(): string
    {
        return $this->themeName;
    }

    /**
     * Get the path to the active theme's assets directory.
     */
    public function getAssetsPath(): string
    {
        return $this->themePath . '/assets';
    }

    // ── Internal ───────────────────────────────────────────────────────

    private function resolveTemplateFile(string $name): ?string
    {
        // Normalize: page--home => page/home, layouts.app => layouts/app
        $normalized = str_replace(['--', '.'], '/', $name);

        $extensions = ['.blade.php', '.twig', '.php'];
        $searchPaths = [
            $this->themePath . '/views',
        ];

        // Add fallback paths
        $base = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3);
        $searchPaths[] = $base . '/resources/PremiumApp/views';

        foreach ($searchPaths as $basePath) {
            foreach ($extensions as $ext) {
                $candidate = $basePath . '/' . $normalized . $ext;
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function renderTwig(string $file, array $context): string
    {
        $loader = new \Twig\Loader\FilesystemLoader(dirname($file));
        $twig = new \Twig\Environment($loader, [
            'cache' => (defined('SPP_APP_DIR') ? SPP_APP_DIR : '.') . '/var/cache/twig',
            'auto_reload' => true,
        ]);
        return $twig->render(basename($file), $context);
    }

    private function resolveDefaultPath(): string
    {
        $base = defined('SPP_APP_DIR') ? SPP_APP_DIR : dirname(__DIR__, 3);

        $activeTheme = 'premium'; // Lekhak's built-in premium theme
        if (class_exists('\\SPP\\SPPConfig')) {
            $configured = \SPP\SPPConfig::get('native_theme');
            if ($configured) $activeTheme = $configured;
        }

        return $base . '/src/lekhak/themes/' . $activeTheme;
    }
}
