<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * ViewStudioService
 * Visual Dynamic Views & Query Studio Engine for SPPDocs.
 * Provides dynamic query building, filtering, sorting, pagination, and multi-format
 * presentation (card grid, data table, unformatted list, JSON API, RSS feed).
 */
class ViewStudioService
{
    public static function getViewsDir(string $projectId): string
    {
        $base = dirname(SPP_BASE_DIR) . '/docs/';
        $dir = $base . $projectId . '/views';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function listViews(string $projectId): array
    {
        $dir = self::getViewsDir($projectId);
        $views = [];

        // Pre-provision default 'all_articles' view if empty
        $defaultViewFile = $dir . '/all_articles.yml';
        if (!file_exists($defaultViewFile)) {
            self::saveView($projectId, 'all_articles', [
                'title' => 'All Documentation Articles',
                'description' => 'Comprehensive grid of all published documentation pages with search and tag filters',
                'collection' => 'page',
                'source_type' => 'docs',
                'display_format' => 'grid',
                'limit' => 12,
                'items_per_page' => 12,
                'sort_by' => 'title',
                'sort_dir' => 'asc',
                'paginate' => true,
                'columns' => ['title', 'description', 'category'],
                'filters' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'published', 'exposed' => false],
                ]
            ]);
        }

        foreach (glob($dir . '/*.yml') as $file) {
            $id = basename($file, '.yml');
            try {
                $parsed = Yaml::parseFile($file) ?: [];
                $views[$id] = array_merge([
                    'id' => $id,
                    'title' => ucfirst(str_replace(['_', '-'], ' ', $id)),
                    'description' => '',
                    'collection' => 'page',
                    'display_format' => 'grid',
                    'limit' => 10,
                    'paginate' => true,
                    'filters' => [],
                    'sorts' => [],
                    'columns' => ['title', 'date', 'author', 'category'],
                    'modified_at' => filemtime($file),
                ], $parsed);
            } catch (\Throwable $e) {}
        }

        ksort($views);
        return $views;
    }

    public static function getView(string $projectId, string $viewId): ?array
    {
        $all = self::listViews($projectId);
        return $all[$viewId] ?? null;
    }

    public static function saveView(string $projectId, string $viewId, array $data): bool
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($viewId));
        if (empty($safeId)) return false;

        $dir = self::getViewsDir($projectId);
        $file = $dir . '/' . $safeId . '.yml';

        $payload = [
            'id' => $safeId,
            'title' => trim($data['title'] ?? ucfirst($safeId)),
            'description' => trim($data['description'] ?? ''),
            'collection' => $data['collection'] ?? ($data['source_type'] ?? 'page'),
            'source_type' => $data['source_type'] ?? ($data['collection'] ?? 'page'),
            'display_format' => $data['display_format'] ?? ($data['layout'] ?? 'grid'),
            'layout' => $data['layout'] ?? ($data['display_format'] ?? 'grid'),
            'limit' => (int)($data['limit'] ?? ($data['items_per_page'] ?? 10)),
            'items_per_page' => (int)($data['items_per_page'] ?? ($data['limit'] ?? 10)),
            'paginate' => !empty($data['paginate']),
            'sort_by' => $data['sort_by'] ?? 'modified_at',
            'sort_dir' => strtolower($data['sort_dir'] ?? 'desc'),
            'filters' => $data['filters'] ?? [],
            'sorts' => $data['sorts'] ?? [],
            'columns' => $data['columns'] ?? ($data['fields'] ?? ['title', 'description']),
            'fields' => $data['fields'] ?? ($data['columns'] ?? ['title', 'description']),
            'updated_at' => time(),
        ];

        return (bool)@file_put_contents($file, Yaml::dump($payload, 4, 2), LOCK_EX);
    }

    public static function deleteView(string $projectId, string $viewId): bool
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($viewId));
        $file = self::getViewsDir($projectId) . '/' . $safeId . '.yml';
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }

    /**
     * Execute a view query dynamically against the collection.
     */
    public static function executeView(string $projectId, string $viewId, array $queryParams = [], string $format = 'html'): array
    {
        $view = self::getView($projectId, $viewId);
        if (!$view) {
            return ['error' => 'View not found', 'data' => [], 'items' => [], 'total' => 0];
        }

        $format = $queryParams['format'] ?? ($format ?: ($view['display_format'] ?? 'grid'));
        $collectionType = $view['collection'] ?? ($view['source_type'] ?? 'page');
        $limit = (int)($queryParams['limit'] ?? ($view['items_per_page'] ?? ($view['limit'] ?? 10)));
        $page = max(1, (int)($queryParams['page'] ?? 1));

        // Scan items from content collection
        $allItems = self::scanFallbackCollection($projectId, $collectionType, $view['filters'] ?? [], $queryParams);

        // Apply sorts
        $sortBy = $queryParams['sort_by'] ?? ($view['sort_by'] ?? 'updated_at');
        $sortDir = strtolower($queryParams['sort_dir'] ?? ($view['sort_dir'] ?? 'desc'));

        usort($allItems, function($a, $b) use ($sortBy, $sortDir) {
            $va = $a[$sortBy] ?? ($a['modified_at'] ?? 0);
            $vb = $b[$sortBy] ?? ($b['modified_at'] ?? 0);
            if ($va == $vb) return 0;
            if ($sortDir === 'asc') {
                return $va < $vb ? -1 : 1;
            }
            return $va > $vb ? -1 : 1;
        });

        $total = count($allItems);
        $offset = ($page - 1) * $limit;
        $pagedItems = array_slice($allItems, $offset, $limit);

        $result = [
            'view' => $view,
            'data' => array_values($pagedItems),
            'items' => array_values($pagedItems),
            'total' => $total,
            'per_page' => $limit,
            'current_page' => $page,
            'total_pages' => (int)ceil($total / max(1, $limit)),
        ];

        // Format specific payloads
        if ($format === 'json') {
            $result['json'] = [
                'view' => $view['title'] ?? $viewId,
                'total' => $total,
                'page' => $page,
                'per_page' => $limit,
                'data' => array_values($pagedItems),
            ];
        } elseif ($format === 'rss') {
            $rss = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $rss .= "<rss version=\"2.0\">\n  <channel>\n";
            $rss .= "    <title>" . htmlspecialchars($view['title'] ?? $viewId) . "</title>\n";
            $rss .= "    <link>" . htmlspecialchars(\SPP\App::getBaseUrl() . '/project/' . $projectId) . "</link>\n";
            $rss .= "    <description>" . htmlspecialchars($view['description'] ?? 'Dynamic View RSS Feed') . "</description>\n";
            foreach ($pagedItems as $it) {
                $rss .= "    <item>\n";
                $rss .= "      <title>" . htmlspecialchars($it['title'] ?? '') . "</title>\n";
                $rss .= "      <description>" . htmlspecialchars($it['description'] ?? ($it['excerpt'] ?? '')) . "</description>\n";
                $rss .= "      <link>" . htmlspecialchars(\SPP\App::getBaseUrl() . '/project/' . $projectId . '/' . ($it['slug'] ?? '')) . "</link>\n";
                $rss .= "    </item>\n";
            }
            $rss .= "  </channel>\n</rss>\n";
            $result['rss'] = $rss;
        }

        return $result;
    }

    private static function scanFallbackCollection(string $projectId, string $type, array $viewFilters = [], array $queryParams = []): array
    {
        $dirsToScan = [];
        $docsDir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId;

        if ($type === 'blog') {
            if (is_dir($docsDir . '/blog')) $dirsToScan[] = $docsDir . '/blog';
        } else {
            if (is_dir($docsDir . '/pages')) $dirsToScan[] = $docsDir . '/pages';
            $handbook = dirname(SPP_BASE_DIR) . '/docs/handbook';
            if ($projectId === 'spp' && is_dir($handbook)) {
                $dirsToScan[] = $handbook;
            }
            if (empty($dirsToScan) && is_dir($docsDir)) {
                $dirsToScan[] = $docsDir;
            }
        }

        $items = [];
        foreach ($dirsToScan as $dir) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iter as $f) {
                if ($f->isFile() && $f->getExtension() === 'md') {
                    $path = $f->getPathname();
                    if (str_contains($path, '.revisions')) continue;

                    $raw = @file_get_contents($path) ?: '';
                    $slug = basename($f->getBasename(), '.md');
                    $meta = [
                        'title' => ucfirst(str_replace(['_', '-'], ' ', $slug)),
                        'slug' => $slug,
                        'description' => '',
                        'status' => 'published',
                        'modified_at' => $f->getMTime(),
                        'updated_at' => $f->getMTime(),
                    ];

                    if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/ms', $raw, $m)) {
                        try {
                            $parsed = Yaml::parse($m[1]) ?: [];
                            $meta = array_merge($meta, $parsed);
                        } catch (\Throwable $e) {}
                    }

                    // Status filter (published by default unless preview)
                    if (empty($queryParams['include_drafts']) && ($meta['status'] ?? 'published') === 'draft') {
                        continue;
                    }

                    // Apply filters
                    $match = true;
                    foreach ($viewFilters as $vf) {
                        $field = $vf['field'] ?? '';
                        $op = strtolower($vf['operator'] ?? '=');
                        $val = $vf['value'] ?? '';

                        // Check exposed filter override
                        if (!empty($vf['exposed']) && isset($queryParams[$field]) && $queryParams[$field] !== '') {
                            $val = $queryParams[$field];
                        }

                        if ($val === '' || empty($field)) continue;

                        $fieldVal = $meta[$field] ?? '';
                        if (is_array($fieldVal)) {
                            $fieldVal = implode(',', $fieldVal);
                        }

                        if ($op === 'like' || $op === 'contains') {
                            if (stripos((string)$fieldVal, (string)$val) === false) {
                                $match = false;
                                break;
                            }
                        } elseif ($op === '!=' || $op === 'not_equals') {
                            if (strtolower((string)$fieldVal) === strtolower((string)$val)) {
                                $match = false;
                                break;
                            }
                        } else { // equals or '='
                            if (strtolower((string)$fieldVal) !== strtolower((string)$val)) {
                                $match = false;
                                break;
                            }
                        }
                    }

                    if ($match) {
                        $items[] = $meta;
                    }
                }
            }
        }

        return $items;
    }
}