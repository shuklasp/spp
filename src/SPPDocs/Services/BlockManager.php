<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * BlockManager
 * Enterprise Theme Regions & Pluggable Blocks Engine for SPPDocs.
 * Extends the core SPP Framework BlockManager primitive.
 */
class BlockManager extends \SPPMod\SPPView\BlockManager
{
    /**
     * Register a custom block plugin from a module.
     */
    public static function registerBlockPlugin(string $pluginId, callable $handler): void
    {
        parent::registerPlugin($pluginId, $handler);
    }

    /**
     * Get path to the blocks configuration YAML file for a project.
     */
    public static function getBlocksFile(string $projectId): string
    {
        $base = dirname(SPP_BASE_DIR) . '/docs/';
        return $base . $projectId . '/blocks.yml';
    }

    /**
     * Get all configured blocks for a project grouped by region.
     */
    public static function getAllBlocks(string $projectId): array
    {
        $file = self::getBlocksFile($projectId);
        if (!file_exists($file)) {
            // Pre-provision clean default blocks if not yet created
            self::saveBlocksConfig($projectId, [
                'regions' => [
                    'sidebar_bottom' => [
                        [
                            'id' => 'default_categories_block',
                            'title' => 'Categories & Topics',
                            'type' => 'taxonomy_tree',
                            'vocab' => 'categories',
                            'weight' => 10,
                            'visibility' => ['paths' => ['*'], 'roles' => ['*']],
                        ]
                    ]
                ]
            ]);
        }

        try {
            $parsed = Yaml::parseFile($file) ?: [];
            return $parsed['regions'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get blocks assigned to a specific region, sorted by weight ascending.
     */
    public static function getRegionBlocks(string $projectId, string $regionName): array
    {
        $all = self::getAllBlocks($projectId);
        $blocks = $all[$regionName] ?? [];

        usort($blocks, function($a, $b) {
            return ($a['weight'] ?? 0) - ($b['weight'] ?? 0);
        });

        return $blocks;
    }

    /**
     * Check if a block is visible for current path and user.
     */
    public static function isBlockVisible(array $block, array $context = []): bool
    {
        $visibility = $block['visibility'] ?? [];
        $currentPath = trim($context['current_path'] ?? ($context['path'] ?? ''), '/');
        $currentUser = $context['user'] ?? ($_SESSION['sppdocs_user'] ?? 'guest');
        $projectId = $context['project_id'] ?? 'spp';

        // 1. Path visibility evaluation
        $paths = (array)($visibility['paths'] ?? ['*']);
        $pathMatch = false;
        if (in_array('*', $paths, true)) {
            $pathMatch = true;
        } else {
            foreach ($paths as $p) {
                $p = trim($p, '/');
                if ($p === '<front>' && ($currentPath === '' || $currentPath === 'project/' . $projectId)) {
                    $pathMatch = true; break;
                }
                if ($p === $currentPath) {
                    $pathMatch = true; break;
                }
                if (str_ends_with($p, '*')) {
                    $prefix = rtrim($p, '*');
                    if (str_starts_with($currentPath, $prefix)) {
                        $pathMatch = true; break;
                    }
                }
            }
        }
        if (!$pathMatch) return false;

        // 2. Role visibility evaluation
        $roles = (array)($visibility['roles'] ?? ['*']);
        if (!in_array('*', $roles, true)) {
            $userRoles = PermissionManager::getUserRoles($projectId, $currentUser);
            $intersect = array_intersect($roles, $userRoles);
            if (empty($intersect) && !PermissionManager::isProjectAdmin($projectId, $currentUser)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save a block placement to a region.
     */
    public static function saveBlock(string $projectId, string $region, array $blockData): bool
    {
        $all = self::getAllBlocks($projectId);
        $blockId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($blockData['id'] ?? uniqid('block_')));

        $blockData['id'] = $blockId;
        $blockData['weight'] = (int)($blockData['weight'] ?? 0);

        // Remove from any existing region first
        foreach ($all as $reg => &$list) {
            foreach ($list as $idx => $b) {
                if ($b['id'] === $blockId) {
                    unset($list[$idx]);
                    $list = array_values($list);
                }
            }
        }

        $all[$region][] = $blockData;
        return self::saveBlocksConfig($projectId, ['regions' => $all]);
    }

    /**
     * Delete a block from a region.
     */
    public static function deleteBlock(string $projectId, string $blockId): bool
    {
        $all = self::getAllBlocks($projectId);
        $found = false;

        foreach ($all as $reg => &$list) {
            foreach ($list as $idx => $b) {
                if ($b['id'] === $blockId) {
                    unset($list[$idx]);
                    $list = array_values($list);
                    $found = true;
                }
            }
        }

        if ($found) {
            return self::saveBlocksConfig($projectId, ['regions' => $all]);
        }
        return false;
    }

    /**
     * Reorder blocks inside a region.
     */
    public static function reorderBlocks(string $projectId, string $region, array $orderedIds): bool
    {
        $all = self::getAllBlocks($projectId);
        $blocks = $all[$region] ?? [];
        $keyed = [];
        foreach ($blocks as $b) {
            $keyed[$b['id']] = $b;
        }

        $newList = [];
        $w = 0;
        foreach ($orderedIds as $id) {
            if (isset($keyed[$id])) {
                $b = $keyed[$id];
                $b['weight'] = $w++;
                $newList[] = $b;
                unset($keyed[$id]);
            }
        }
        foreach ($keyed as $remaining) {
            $remaining['weight'] = $w++;
            $newList[] = $remaining;
        }

        $all[$region] = $newList;
        return self::saveBlocksConfig($projectId, ['regions' => $all]);
    }

    private static function saveBlocksConfig(string $projectId, array $data): bool
    {
        $file = self::getBlocksFile($projectId);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return (bool)@file_put_contents($file, Yaml::dump($data, 5, 2), LOCK_EX);
    }

    /**
     * Render a theme region and all its visible blocks.
     */
    public static function renderRegion(string $projectId, mixed $regionName = '', array $context = [], string $partial = 'partials/region.blade.php'): string
    {
        $regionNameStr = (string)$regionName;
        $blocks = self::getRegionBlocks($projectId, $regionNameStr);
        if (empty($blocks)) {
            return '';
        }

        $visibleBlocks = [];
        foreach ($blocks as $b) {
            if (self::isBlockVisible($b, $context)) {
                $blockType = $b['type'] ?? 'alert';
                $blockData = $b;

                // Prepare block specific data
                if ($blockType === 'view' && !empty($b['view_id'])) {
                    $blockData['viewResult'] = ViewStudioService::executeView($projectId, $b['view_id'], $context);
                } elseif ($blockType === 'taxonomy_tree') {
                    $vocab = $b['vocab'] ?? 'categories';
                    $blockData['tree'] = TaxonomyService::getHierarchyTree($projectId, $vocab);
                    $blockData['vocab'] = $vocab;
                } elseif ($blockType === 'custom' && isset(self::$customPlugins[$b['plugin_id'] ?? ''])) {
                    $handler = self::$customPlugins[$b['plugin_id']];
                    $blockData['custom_html'] = call_user_func($handler, $context);
                }

                $visibleBlocks[] = $blockData;
            }
        }

        if (empty($visibleBlocks)) {
            return '';
        }

        $context['project_id'] = $projectId;
        return parent::renderRegion($regionName, $visibleBlocks, $context, 'partials/region.blade.php');
    }
}