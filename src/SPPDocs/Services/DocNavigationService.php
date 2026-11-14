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

    /**
     * Get a linear list of all documentation pages/chapters in order.
     */
    public static function getFlattenedPages(array $project = [], string $projectId = '', string $version = ''): array
    {
        $sidebar = $project['sidebar'] ?? [];
        if (empty($sidebar) && !empty($project['pages_dir'])) {
            $pDir = $project['pages_dir'];
            if (strpos($pDir, 'C:/') === 0 || strpos($pDir, 'c:/') === 0) {
                $pDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $pDir);
            }
            if (!str_starts_with($pDir, '/') && !str_contains($pDir, ':\\')) {
                $pDir = dirname(SPP_BASE_DIR) . '/' . $pDir;
            }
            $sidebar = self::buildSidebar($pDir);
        }

        $pages = [];
        if (!empty($sidebar) && is_array($sidebar)) {
            $baseUrl = class_exists(\SPP\App::class) ? \SPP\App::getBaseUrl() : '';
            $effectiveVersion = !empty($version) ? $version : ($project['default_version'] ?? 'v1');
            $effectiveProjectId = !empty($projectId) ? $projectId : ($project['id'] ?? 'spp');

            foreach ($sidebar as $category => $links) {
                if (!is_array($links)) continue;
                foreach ($links as $link) {
                    if (is_array($link)) {
                        foreach ($link as $title => $slug) {
                            if (empty($slug)) continue;
                            $cleanSlug = trim($slug, '/');
                            $cleanTitle = trim($title);
                            $urlPath = "docs/{$effectiveProjectId}/{$effectiveVersion}/{$cleanSlug}";
                            $pages[] = [
                                'title' => $cleanTitle,
                                'slug' => $cleanSlug,
                                'category' => $category,
                                'url' => $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' . ltrim($urlPath, '/') : '/' . $urlPath,
                            ];
                        }
                    } elseif (is_string($link)) {
                        $cleanSlug = trim($link, '/');
                        $cleanTitle = ucfirst(str_replace(['-', '_'], ' ', $cleanSlug));
                        $urlPath = "docs/{$effectiveProjectId}/{$effectiveVersion}/{$cleanSlug}";
                        $pages[] = [
                            'title' => $cleanTitle,
                            'slug' => $cleanSlug,
                            'category' => $category,
                            'url' => $baseUrl !== '' ? rtrim($baseUrl, '/') . '/' . ltrim($urlPath, '/') : '/' . $urlPath,
                        ];
                    }
                }
            }
        }

        return $pages;
    }

    /**
     * Determine whether the given project or document represents a book.
     */
    public static function isBook(array $project = [], string $projectId = '', array $frontmatter = []): bool
    {
        // 1. Explicit document frontmatter override
        if (isset($frontmatter['type']) && strtolower((string)$frontmatter['type']) === 'book') {
            return true;
        }
        if (isset($frontmatter['is_book'])) {
            return !empty($frontmatter['is_book']);
        }
        if (isset($frontmatter['chapter']) || isset($frontmatter['chapter_number'])) {
            return true;
        }

        // 2. Explicit project configuration
        if (!empty($project['is_book'])) {
            return true;
        }
        if (isset($project['type']) && strtolower((string)$project['type']) === 'book') {
            return true;
        }
        if (isset($project['format']) && strtolower((string)$project['format']) === 'book') {
            return true;
        }
        if (isset($project['profile']) && strtolower((string)$project['profile']) === 'book') {
            return true;
        }
        if (!empty($project['features']['is_book'])) {
            return true;
        }
        if (!empty($project['book_export']) || isset($project['handbook']) || isset($project['book'])) {
            return true;
        }

        // 3. Project ID convention (e.g. 'book', 'book-*', '*-book', 'spp')
        if (!empty($projectId)) {
            $cleanId = strtolower(trim($projectId));
            if ($cleanId === 'book' || str_starts_with($cleanId, 'book-') || str_starts_with($cleanId, 'book_') || str_ends_with($cleanId, '-book') || str_ends_with($cleanId, '_book') || $cleanId === 'spp') {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve previous and next pages relative to the current page.
     * Only returns adjacent pages when the target project/document is a book.
     */
    public static function getAdjacentPages(array $project, string $currentPage, string $projectId = '', string $version = '', array $frontmatter = []): array
    {
        // Enforce constraint: pagination links appear only in books and not on general documents
        if (!self::isBook($project, $projectId, $frontmatter)) {
            return ['prev' => null, 'next' => null];
        }

        $pages = self::getFlattenedPages($project, $projectId, $version);
        if (empty($pages)) {
            return ['prev' => null, 'next' => null];
        }

        $cleanCurrent = strtolower(trim(preg_replace('/\.md$/', '', $currentPage), '/'));
        $isIndexAlias = in_array($cleanCurrent, ['index', 'readme']);

        $matchedIndex = -1;
        foreach ($pages as $idx => $p) {
            $pSlug = strtolower(trim(preg_replace('/\.md$/', '', $p['slug']), '/'));
            if ($pSlug === $cleanCurrent) {
                $matchedIndex = $idx;
                break;
            }
            if ($isIndexAlias && in_array($pSlug, ['index', 'readme'])) {
                $matchedIndex = $idx;
                break;
            }
            if (basename($pSlug) === basename($cleanCurrent)) {
                $matchedIndex = $idx;
                break;
            }
        }

        if ($matchedIndex === -1) {
            return ['prev' => null, 'next' => null];
        }

        $prev = ($matchedIndex > 0) ? $pages[$matchedIndex - 1] : null;
        $next = ($matchedIndex < count($pages) - 1) ? $pages[$matchedIndex + 1] : null;

        return [
            'prev' => $prev,
            'next' => $next,
        ];
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
