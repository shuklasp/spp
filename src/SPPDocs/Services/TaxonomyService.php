<?php

namespace App\SPPDocs\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * TaxonomyService
 * Hierarchical Taxonomy Vocabularies & Entity Reference Engine for SPPDocs.
 * Supports nested parent-child category trees, term metadata (colors, icons, descriptions),
 * and automatic term archive page document resolution.
 */
class TaxonomyService
{
    public static function getTaxonomyDir(string $projectId): string
    {
        $base = dirname(SPP_BASE_DIR) . '/docs/';
        $dir = $base . $projectId . '/taxonomy';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function getVocabularies(string $projectId): array
    {
        return self::listVocabularies($projectId);
    }

    public static function listVocabularies(string $projectId): array
    {
        $dir = self::getTaxonomyDir($projectId);
        $vocabs = [];

        // Pre-provision default 'categories' and 'tags' vocabularies if empty
        $catFile = $dir . '/categories.yml';
        if (!file_exists($catFile)) {
            self::saveVocabulary($projectId, 'categories', [
                'title' => 'Documentation Categories',
                'hierarchical' => true,
                'terms' => [
                    'architecture' => [
                        'name' => 'Architecture & Core',
                        'slug' => 'architecture',
                        'parent' => null,
                        'description' => 'Kernel, scheduler, and system architecture',
                        'color' => '#3b82f6',
                        'icon' => '🏛️',
                    ],
                    'tutorials' => [
                        'name' => 'Tutorials & Guides',
                        'slug' => 'tutorials',
                        'parent' => null,
                        'description' => 'Hands-on guided walkthroughs',
                        'color' => '#10b981',
                        'icon' => '📚',
                    ],
                    'security' => [
                        'name' => 'Security & RBAC',
                        'slug' => 'security',
                        'parent' => 'architecture',
                        'description' => 'Authentication, authorization, and audit logs',
                        'color' => '#ef4444',
                        'icon' => '🛡️',
                    ],
                ]
            ]);
        }

        $tagFile = $dir . '/tags.yml';
        if (!file_exists($tagFile)) {
            self::saveVocabulary($projectId, 'tags', [
                'title' => 'Content Tags',
                'hierarchical' => false,
                'terms' => [
                    'api' => ['name' => 'API', 'slug' => 'api', 'parent' => null, 'color' => '#8b5cf6', 'icon' => '⚡'],
                    'database' => ['name' => 'Database', 'slug' => 'database', 'parent' => null, 'color' => '#f59e0b', 'icon' => '💾'],
                    'frontend' => ['name' => 'Frontend', 'slug' => 'frontend', 'parent' => null, 'color' => '#06b6d4', 'icon' => '🎨'],
                ]
            ]);
        }

        foreach (glob($dir . '/*.yml') as $file) {
            $id = basename($file, '.yml');
            try {
                $parsed = Yaml::parseFile($file) ?: [];
                $vocabs[$id] = array_merge([
                    'id' => $id,
                    'title' => ucfirst($id),
                    'hierarchical' => true,
                    'terms' => [],
                ], $parsed);
            } catch (\Throwable $e) {}
        }

        ksort($vocabs);
        return $vocabs;
    }

    public static function getVocabulary(string $projectId, string $vocab): ?array
    {
        $all = self::listVocabularies($projectId);
        return $all[$vocab] ?? null;
    }

    public static function saveVocabulary(string $projectId, string $vocab, array $data): bool
    {
        $safeVocab = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($vocab));
        if (empty($safeVocab)) return false;

        $dir = self::getTaxonomyDir($projectId);
        $file = $dir . '/' . $safeVocab . '.yml';

        $payload = [
            'id' => $safeVocab,
            'title' => trim($data['title'] ?? ucfirst($safeVocab)),
            'description' => trim($data['description'] ?? ''),
            'hierarchical' => !empty($data['hierarchical']),
            'terms' => $data['terms'] ?? [],
            'updated_at' => time(),
        ];

        return (bool)@file_put_contents($file, Yaml::dump($payload, 4, 2), LOCK_EX);
    }

    public static function saveTerm(string $projectId, string $vocab, $slugOrData, array $termData = []): bool
    {
        $v = self::getVocabulary($projectId, $vocab);
        if (!$v) return false;

        if (is_array($slugOrData)) {
            $termData = $slugOrData;
            $slug = $termData['slug'] ?? '';
        } else {
            $slug = (string)$slugOrData;
        }

        $safeSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($slug));
        if (empty($safeSlug)) return false;

        $terms = $v['terms'] ?? [];
        $terms[$safeSlug] = [
            'name' => trim($termData['name'] ?? ucfirst($safeSlug)),
            'slug' => $safeSlug,
            'parent' => !empty($termData['parent']) ? trim($termData['parent']) : null,
            'description' => trim($termData['description'] ?? ''),
            'color' => trim($termData['color'] ?? '#3b82f6'),
            'icon' => trim($termData['icon'] ?? '🏷️'),
        ];

        $v['terms'] = $terms;
        return self::saveVocabulary($projectId, $vocab, $v);
    }

    public static function deleteTerm(string $projectId, string $vocab, string $slug): bool
    {
        $v = self::getVocabulary($projectId, $vocab);
        if (!$v) return false;

        $terms = $v['terms'] ?? [];
        if (isset($terms[$slug])) {
            unset($terms[$slug]);
            // Re-parent children to null if their parent was deleted
            foreach ($terms as &$t) {
                if (($t['parent'] ?? null) === $slug) {
                    $t['parent'] = null;
                }
            }
            $v['terms'] = $terms;
            return self::saveVocabulary($projectId, $vocab, $v);
        }
        return false;
    }

    /**
     * Build nested parent-child tree hierarchy for display.
     */
    public static function getHierarchyTree(string $projectId, string $vocab): array
    {
        $v = self::getVocabulary($projectId, $vocab);
        if (!$v) return [];

        $terms = $v['terms'] ?? [];
        $tree = [];
        $childrenMap = [];

        foreach ($terms as $slug => $t) {
            $parent = $t['parent'] ?? null;
            if ($parent && isset($terms[$parent])) {
                $childrenMap[$parent][] = $t;
            } else {
                $tree[] = $t;
            }
        }

        // Attach children recursively
        $attachChildren = function(&$nodes) use (&$attachChildren, $childrenMap) {
            foreach ($nodes as &$node) {
                $slug = $node['slug'];
                if (isset($childrenMap[$slug])) {
                    $node['children'] = $childrenMap[$slug];
                    $attachChildren($node['children']);
                } else {
                    $node['children'] = [];
                }
            }
        };

        $attachChildren($tree);
        return $tree;
    }

    /**
     * Find all documents associated with a taxonomy term.
     */
    public static function getDocumentsForTerm(string $projectId, string $vocab, string $termSlug): array
    {
        $docsDir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId;
        $matching = [];

        // Scan pages and blog
        $dirsToScan = [];
        if (is_dir($docsDir . '/pages')) $dirsToScan[] = $docsDir . '/pages';
        if (is_dir($docsDir . '/blog')) $dirsToScan[] = $docsDir . '/blog';

        // Check handbook or root
        $handbookDir = dirname(SPP_BASE_DIR) . '/docs/handbook';
        if ($projectId === 'spp' && is_dir($handbookDir)) {
            $dirsToScan[] = $handbookDir;
        }

        foreach ($dirsToScan as $dir) {
            $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $path = $file->getPathname();
                    if (str_contains($path, '.revisions')) continue;

                    $raw = file_get_contents($path);
                    if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/ms', $raw, $m)) {
                        try {
                            $fm = Yaml::parse($m[1]) ?: [];
                            $assigned = false;

                            // Check category
                            if (!empty($fm['category'])) {
                                $c = is_string($fm['category']) ? strtolower($fm['category']) : '';
                                if ($c === strtolower($termSlug)) $assigned = true;
                            }

                            // Check tags
                            if (!empty($fm['tags'])) {
                                $tags = is_array($fm['tags']) ? $fm['tags'] : array_map('trim', explode(',', $fm['tags']));
                                foreach ($tags as $tag) {
                                    if (strtolower(trim($tag)) === strtolower($termSlug)) {
                                        $assigned = true;
                                        break;
                                    }
                                }
                            }

                            // Check custom taxonomy field
                            if (!empty($fm[$vocab]) && strtolower((string)$fm[$vocab]) === strtolower($termSlug)) {
                                $assigned = true;
                            }

                            if ($assigned) {
                                $slug = basename($file->getBasename(), '.md');
                                $matching[] = [
                                    'title' => $fm['title'] ?? ucfirst(str_replace(['_', '-'], ' ', $slug)),
                                    'slug' => $slug,
                                    'description' => $fm['description'] ?? ($fm['excerpt'] ?? ''),
                                    'date' => $fm['date'] ?? filemtime($path),
                                    'author' => $fm['author'] ?? 'SPPDocs',
                                    'category' => $fm['category'] ?? '',
                                ];
                            }
                        } catch (\Throwable $e) {}
                    }
                }
            }
        }

        return $matching;
    }
}