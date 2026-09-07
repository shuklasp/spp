<?php

namespace App\SPPDocs\Services;

/**
 * DocNavigationService
 * Automatically scans the documentation pages directory and builds a hierarchical,
 * ordered sidebar structure from filesystem structure and Markdown YAML frontmatter.
 */
class DocNavigationService
{
    public static function buildSidebar(string $pagesDir): array
    {
        if (!is_dir($pagesDir)) {
            return [];
        }

        $sidebar = [];
        $files = self::scanRecursive($pagesDir);

        // Group files by directory or frontmatter group
        $groups = [];
        foreach ($files as $file) {
            $relPath = str_replace([$pagesDir . '/', $pagesDir . '\\'], '', $file);
            $slug = preg_replace('/\.md$/', '', $relPath);
            $slug = str_replace('\\', '/', $slug);

            $meta = self::extractFrontmatter($file);
            $title = $meta['title'] ?? ucfirst(basename($slug));
            $order = (int) ($meta['order'] ?? 999);
            $group = $meta['group'] ?? null;

            if (!$group) {
                $parts = explode('/', $slug);
                if (count($parts) > 1) {
                    $group = ucfirst(str_replace(['-', '_'], ' ', $parts[0]));
                } else {
                    $group = 'General';
                }
            }

            $groups[$group][] = [
                'title' => $title,
                'slug' => $slug,
                'order' => $order
            ];
        }

        // Sort items inside each group by order
        foreach ($groups as $groupName => $items) {
            usort($items, fn($a, $b) => $a['order'] <=> $b['order']);
            $sidebarGroup = [];
            foreach ($items as $item) {
                $sidebarGroup[] = [$item['title'] => $item['slug']];
            }
            $sidebar[$groupName] = $sidebarGroup;
        }

        return $sidebar;
    }

    private static function scanRecursive(string $dir): array
    {
        $results = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $results = array_merge($results, self::scanRecursive($path));
            } elseif (str_ends_with($item, '.md')) {
                $results[] = $path;
            }
        }

        return $results;
    }

    private static function extractFrontmatter(string $filePath): array
    {
        $content = @file_get_contents($filePath);
        if (!$content) return [];

        if (preg_match('/^---\r?\n(.*?)\r?\n---/s', $content, $matches)) {
            try {
                return \Symfony\Component\Yaml\Yaml::parse($matches[1]) ?: [];
            } catch (\Exception $e) {
                return [];
            }
        }

        return [];
    }
}
