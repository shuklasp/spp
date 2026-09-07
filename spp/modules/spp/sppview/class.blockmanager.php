<?php

namespace SPPMod\SPPView;

use Symfony\Component\Yaml\Yaml;

/**
 * Class BlockManager
 * Core presentation layout engine for theme regions, pluggable block widgets,
 * and path/role visibility evaluation across all SPP applications.
 */
class BlockManager
{
    protected static array $customPlugins = [];

    /**
     * Register a custom block plugin provider.
     */
    public static function registerPlugin(string $pluginId, callable $handler): void
    {
        self::$customPlugins[$pluginId] = $handler;
    }

    /**
     * Get all custom plugins registered in the runtime.
     */
    public static function getPlugins(): array
    {
        return self::$customPlugins;
    }

    /**
     * Check if a block passes visibility criteria for the given request context.
     */
    public static function isBlockVisible(array $block, array $context = []): bool
    {
        $visibility = $block['visibility'] ?? [];
        $currentPath = trim($context['current_path'] ?? ($context['path'] ?? ($_SERVER['REQUEST_URI'] ?? '')), '/');
        
        // Strip query string from path for clean evaluation
        if (($pos = strpos($currentPath, '?')) !== false) {
            $currentPath = substr($currentPath, 0, $pos);
        }

        // 1. Path pattern evaluation
        $paths = (array)($visibility['paths'] ?? ['*']);
        $pathMatch = false;

        if (in_array('*', $paths, true) || empty($paths)) {
            $pathMatch = true;
        } else {
            foreach ($paths as $p) {
                $p = trim((string)$p, '/');
                if ($p === '<front>' && ($currentPath === '' || $currentPath === 'home' || $currentPath === 'index.php')) {
                    $pathMatch = true;
                    break;
                }
                if ($p === $currentPath) {
                    $pathMatch = true;
                    break;
                }
                if (str_ends_with($p, '*')) {
                    $prefix = rtrim($p, '*');
                    if (str_starts_with($currentPath, $prefix)) {
                        $pathMatch = true;
                        break;
                    }
                }
            }
        }

        if (!$pathMatch) {
            return false;
        }

        // 2. Role evaluation
        $roles = (array)($visibility['roles'] ?? ['*']);
        if (!in_array('*', $roles, true) && !empty($roles)) {
            $currentUser = $context['user'] ?? ($_SESSION['user'] ?? ($_SESSION['sppdocs_user'] ?? 'guest'));
            $userRoles = (array)($context['user_roles'] ?? ($_SESSION['user_roles'] ?? ['guest']));

            if (!empty($context['role_check_callback']) && is_callable($context['role_check_callback'])) {
                if (!call_user_func($context['role_check_callback'], $currentUser, $roles)) {
                    return false;
                }
            } else {
                $intersect = array_intersect($roles, $userRoles);
                if (empty($intersect) && !in_array('admin', $userRoles, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Filter and sort blocks assigned to a region.
     */
    public static function prepareRegionBlocks(array $blocks, array $context = []): array
    {
        $visible = [];
        foreach ($blocks as $block) {
            if (self::isBlockVisible($block, $context)) {
                // If block is a custom plugin, resolve its dynamic content
                $type = $block['type'] ?? 'custom';
                $pluginId = $block['plugin_id'] ?? $type;
                if (isset(self::$customPlugins[$pluginId])) {
                    $block['custom_content'] = call_user_func(self::$customPlugins[$pluginId], $block, $context);
                }
                $visible[] = $block;
            }
        }

        usort($visible, function ($a, $b) {
            return ($a['weight'] ?? 0) - ($b['weight'] ?? 0);
        });

        return $visible;
    }

    /**
     * Render a theme region using standalone external partials.
     * Adheres strictly to Zero Inline HTML Literals constraint.
     */
    public static function renderRegion(string $region, mixed $blocks = [], array $context = [], string $partial = 'partials/region.blade.php'): string
    {
        $preparedBlocks = is_array($blocks) ? self::prepareRegionBlocks($blocks, $context) : [];
        if (empty($preparedBlocks)) {
            return '';
        }

        $renderData = array_merge($context, [
            'region' => $region,
            'blocks' => $preparedBlocks,
            'context' => $context,
        ]);

        if (class_exists('\\SPPMod\\Drishyam\\TemplateMacros')) {
            return \SPPMod\Drishyam\TemplateMacros::spppartial($partial, $renderData);
        }

        $app = class_exists('\\SPP\\Scheduler') ? (\SPP\Scheduler::getContext() ?: 'default') : 'default';
        $file = ViewLocator::locate($partial, $app);
        if ($file && file_exists($file)) {
            extract($renderData, EXTR_SKIP);
            ob_start();
            include $file;
            return ob_get_clean() ?: '';
        }

        return '';
    }
}
