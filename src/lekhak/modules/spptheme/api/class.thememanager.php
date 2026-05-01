<?php
namespace SPPMod\SppTheme\Api;

use Symfony\Component\Yaml\Yaml;

/**
 * ThemeManager
 * 
 * Handles theme discovery, region management, and layout rendering.
 * Locally contained within the application architecture.
 */
class ThemeManager {
    private static $activeTheme = null;
    private static $regions = [];
    private static $themeData = [];

    /**
     * Set the active theme for the current request.
     */
    public static function setTheme($themeName) {
        $app = \SPP\App::getApp();
        
        // Resolve Theme Directory relative to App Src
        $themeDir = $app->getAppSrcDir() . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $themeName;
        
        if (is_dir($themeDir)) {
            self::$activeTheme = $themeDir;
            $manifest = $themeDir . DIRECTORY_SEPARATOR . 'theme.yml';
            if (file_exists($manifest)) {
                self::$themeData = Yaml::parseFile($manifest);
            }
            return true;
        }
        return false;
    }

    /**
     * Add content to a theme region.
     */
    public static function setRegion($region, $content) {
        self::$regions[$region] = (self::$regions[$region] ?? '') . $content;
    }

    /**
     * Get content of a theme region.
     */
    public static function getRegion($region) {
        return self::$regions[$region] ?? '';
    }

    /**
     * Render the page using the active theme's layout.
     */
    public static function renderWithTheme($pageContent, $pageData = []) {
        if (!self::$activeTheme) {
            echo $pageContent;
            return;
        }

        self::setRegion('content', $pageContent);
        
        // Prepare template variables
        $vars = array_merge($pageData, self::$regions);
        $vars['theme_path'] = self::getThemePublicPath();
        
        $layoutFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'layout.blade.php';
        if (!file_exists($layoutFile)) {
            $layoutFile = self::$activeTheme . DIRECTORY_SEPARATOR . 'layout.php';
        }

        if (file_exists($layoutFile)) {
            if (str_ends_with($layoutFile, '.blade.php') && \SPP\Module::isEnabled('sppblade')) {
                echo \SPPMod\SPPBlade\SPPBlade::render($layoutFile, $vars);
            } else {
                extract($vars);
                include($layoutFile);
            }
        } else {
            echo $pageContent;
        }
    }

    private static function getThemePublicPath() {
        $baseUrl = defined('APP_BASE_URI') ? APP_BASE_URI : '';
        $relPath = substr(self::$activeTheme, strlen(SPP_APP_DIR));
        return rtrim($baseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $relPath), '/');
    }
}
