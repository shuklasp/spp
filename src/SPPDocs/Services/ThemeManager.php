<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;
use SPPMod\SPPView\ViewLocator;

/**
 * ThemeManager
 * Enterprise Theme Inheritance, Custom Regions, and Template Suggestion Cascade Engine for SPPDocs.
 */
class ThemeManager
{
    /**
     * Default standard regions for the default theme.
     */
    public const DEFAULT_REGIONS = [
        'header_top' => 'Header Top (Alerts & Announcements)',
        'sidebar_top' => 'Sidebar Top (Above Navigation)',
        'sidebar_bottom' => 'Sidebar Bottom (Widgets & Links)',
        'content_top' => 'Content Top (Breadcrumbs & Badges)',
        'content_bottom' => 'Content Bottom (Feedback & Related Articles)',
        'footer_bottom' => 'Footer Bottom (Disclaimers & Copyright)',
    ];

    /**
     * Get active theme name for a project.
     */
    public static function getActiveThemeName(string $projectId): string
    {
        $configFile = 'C:/projects/apache/school1/etc/apps/SPPDocs/config.yml';
        if (file_exists($configFile)) {
            try {
                $cfg = Yaml::parseFile($configFile);
                if (!empty($cfg['projects'][$projectId]['theme'])) {
                    return $cfg['projects'][$projectId]['theme'];
                }
            } catch (\Throwable $e) {}
        }

        $projFile = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/project.yml';
        if (file_exists($projFile)) {
            try {
                $proj = Yaml::parseFile($projFile);
                if (!empty($proj['theme'])) {
                    return $proj['theme'];
                }
            } catch (\Throwable $e) {}
        }

        return 'default';
    }

    /**
     * Get all registered theme search roots.
     */
    public static function getThemeSearchRoots(string $projectId): array
    {
        $roots = [];

        // 1. Project-specific themes
        $projThemes = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/themes';
        if (is_dir($projThemes)) $roots[] = $projThemes;

        // 2. Global application themes
        $globalThemes = dirname(SPP_BASE_DIR) . '/themes';
        if (is_dir($globalThemes)) $roots[] = $globalThemes;

        // 3. Core SPPDocs built-in themes
        $coreThemes = dirname(__DIR__) . '/resources/themes';
        if (is_dir($coreThemes)) $roots[] = $coreThemes;

        return $roots;
    }

    /**
     * Get active theme definition and manifest.
     */
    public static function getTheme(string $projectId, ?string $themeName = null): array
    {
        $themeName = $themeName ?: self::getActiveThemeName($projectId);
        $roots = self::getThemeSearchRoots($projectId);

        foreach ($roots as $root) {
            $themeDir = $root . '/' . $themeName;
            $manifest = $themeDir . '/theme.yml';
            if (file_exists($manifest)) {
                try {
                    $parsed = Yaml::parseFile($manifest) ?: [];
                    return array_merge([
                        'id' => $themeName,
                        'name' => ucfirst($themeName),
                        'parent' => 'default',
                        'description' => '',
                        'version' => '1.0.0',
                        'path' => $themeDir,
                        'regions' => null, // null means use defaults
                        'stylesheets' => [],
                        'scripts' => [],
                    ], $parsed);
                } catch (\Throwable $e) {}
            }
        }

        return [
            'id' => 'default',
            'name' => 'Default SPPDocs Theme',
            'parent' => null,
            'description' => 'Clean, modern developer documentation theme',
            'version' => '1.0.0',
            'path' => dirname(__DIR__) . '/resources/themes/default',
            'regions' => null,
            'stylesheets' => [],
            'scripts' => [],
        ];
    }

    /**
     * Get regions for the project's active theme.
     * If theme declares custom regions in theme.yml, returns them; otherwise falls back to default 6 regions.
     */
    public static function getThemeRegions(string $projectId): array
    {
        $theme = self::getTheme($projectId);
        $themeRegions = $theme['regions'] ?? null;

        if (is_array($themeRegions) && !empty($themeRegions)) {
            $formatted = [];
            foreach ($themeRegions as $key => $label) {
                if (is_int($key)) {
                    $formatted[$label] = ucfirst(str_replace('_', ' ', $label));
                } else {
                    $formatted[$key] = $label;
                }
            }
            return $formatted;
        }

        return self::DEFAULT_REGIONS;
    }

    /**
     * Resolve document template using template hook suggestions cascade.
     */
    public static function resolveTemplate(string $projectId, string $slug, string $type = 'page'): string
    {
        $theme = self::getTheme($projectId);
        $cleanSlug = basename($slug);
        $cleanType = basename($type);

        $candidates = [];
        if (!empty($theme['path'])) {
            $candidates[] = $theme['path'] . "/views/docs/{$projectId}/{$cleanSlug}.blade.php";
            $candidates[] = $theme['path'] . "/views/docs/{$cleanSlug}.blade.php";
        }
        $candidates[] = dirname(SPP_BASE_DIR) . "/docs/{$projectId}/templates/{$cleanSlug}.blade.php";
        if (!empty($theme['path'])) {
            $candidates[] = $theme['path'] . "/views/docs/{$cleanType}.blade.php";
            $candidates[] = $theme['path'] . "/views/docs.blade.php";
        }

        return ViewLocator::cascade('docs', $candidates, 'SPPDocs');
    }

    /**
     * Resolve view template using template suggestions.
     */
    public static function resolveViewTemplate(string $projectId, string $viewId): string
    {
        $theme = self::getTheme($projectId);
        $cleanViewId = basename($viewId);

        $candidates = [];
        if (!empty($theme['path'])) {
            $candidates[] = $theme['path'] . "/views/views/{$cleanViewId}.blade.php";
            $candidates[] = $theme['path'] . "/views/views/show.blade.php";
        }

        return ViewLocator::cascade('views.show', $candidates, 'SPPDocs');
    }

    /**
     * Resolve taxonomy template using template suggestions.
     */
    public static function resolveTaxonomyTemplate(string $projectId, string $vocab): string
    {
        $theme = self::getTheme($projectId);
        $cleanVocab = basename($vocab);

        $candidates = [];
        if (!empty($theme['path'])) {
            $candidates[] = $theme['path'] . "/views/taxonomy/{$cleanVocab}.blade.php";
            $candidates[] = $theme['path'] . "/views/taxonomy/archive.blade.php";
        }

        return ViewLocator::cascade('taxonomy.archive', $candidates, 'SPPDocs');
    }

    /**
     * Get CSS and JS asset paths declared by the active theme.
     */
    public static function getThemeAssets(string $projectId): array
    {
        $theme = self::getTheme($projectId);
        $css = [];
        $js = [];

        if (!empty($theme['stylesheets'])) {
            foreach ($theme['stylesheets'] as $s) {
                if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://') || str_starts_with($s, '/')) {
                    $css[] = $s;
                } else {
                    $css[] = \SPP\App::getBaseUrl() . '/themes/' . $theme['id'] . '/' . ltrim($s, '/');
                }
            }
        }

        if (!empty($theme['scripts'])) {
            foreach ($theme['scripts'] as $s) {
                if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://') || str_starts_with($s, '/')) {
                    $js[] = $s;
                } else {
                    $js[] = \SPP\App::getBaseUrl() . '/themes/' . $theme['id'] . '/' . ltrim($s, '/');
                }
            }
        }

        return ['css' => $css, 'js' => $js];
    }
}