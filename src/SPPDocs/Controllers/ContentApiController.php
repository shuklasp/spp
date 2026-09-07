<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Classes\SPPCMS;
use SPP\Response;

class ContentApiController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/api/v1/content', method: 'GET')]
    public function list()
    {
        $projectId = $_GET['project'] ?? $_GET['projectId'] ?? '';
        if (empty($projectId) || !isset($this->config['projects'][$projectId])) {
            Response::json(['error' => 'Valid project parameter is required.'], 400);
            return;
        }

        $project = $this->config['projects'][$projectId];
        $type = $_GET['type'] ?? 'docs';
        $version = $_GET['version'] ?? ($project['default_version'] ?? 'v1');
        $tag = $_GET['tag'] ?? null;
        $author = $_GET['author'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));

        $items = [];
        $dir = '';

        if ($type === 'blog') {
            $dir = $project['blog_dir'] ?? '';
        } elseif ($type === 'docs') {
            $dir = $this->resolveDocsDir($projectId, $version);
        } else {
            // Check collections
            $collections = $project['collections'] ?? [];
            if (isset($collections[$type])) {
                $cDir = $collections[$type]['path'] ?? ('collections/' . $type);
                $dir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/' . $cDir;
            } else {
                $dir = $this->resolveDocsDir($projectId, $version);
            }
        }

        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
        }

        if (is_dir($dir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $raw = @file_get_contents($file->getPathname()) ?: '';
                    $frontmatter = $this->extractFrontmatter($raw);

                    // Status filter: only published by default
                    $status = $frontmatter['status'] ?? 'published';
                    if ($status === 'draft') {
                        continue;
                    }
                    if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                        continue;
                    }

                    // Tag filter
                    if (!empty($tag)) {
                        $tags = (array)($frontmatter['tags'] ?? []);
                        if (!in_array($tag, $tags, true)) {
                            continue;
                        }
                    }

                    // Author filter
                    if (!empty($author)) {
                        $fAuthor = $frontmatter['author'] ?? '';
                        if (stripos($fAuthor, $author) === false) {
                            continue;
                        }
                    }

                    $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                    $slug = substr($relPath, 0, -3);

                    // Body without frontmatter
                    $cleanBody = preg_replace('/^---\r?\n.*?\r?\n---\r?\n/s', '', $raw);
                    $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($cleanBody))), 0, 200);

                    $user = $_SESSION['sppdocs_user'] ?? $_SESSION['user'] ?? null;
                    $sanitizedFm = \App\SPPDocs\Services\SchemaStudioService::filterFieldsForUser($projectId, $type, $frontmatter, $user);

                    $items[] = [
                        'slug' => $slug,
                        'title' => $sanitizedFm['title'] ?? ucfirst(str_replace('-', ' ', basename($slug))),
                        'description' => $sanitizedFm['description'] ?? '',
                        'excerpt' => $excerpt,
                        'frontmatter' => $sanitizedFm,
                        'url' => \SPP\App::getBaseUrl() . '/docs/' . rawurlencode($projectId) . '/' . rawurlencode($version) . '/' . $slug,
                        'published_at' => !empty($frontmatter['date']) ? date('c', strtotime($frontmatter['date'])) : date('c', $file->getMTime()),
                        'updated_at' => !empty($frontmatter['updated']) ? date('c', strtotime($frontmatter['updated'])) : date('c', $file->getMTime()),
                    ];
                }
            }
        }

        // Sort by updated_at descending
        usort($items, function ($a, $b) {
            return strcmp($b['updated_at'], $a['updated_at']);
        });

        $total = count($items);
        $totalPages = (int)ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $paginated = array_slice($items, $offset, $limit);

        $payload = [
            'project' => $projectId,
            'type' => $type,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'items' => $paginated,
        ];

        $etag = '"' . md5(json_encode($payload)) . '"';
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }

        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=300');
        Response::json($payload);
    }

    #[Route('/api/v1/content/show', method: 'GET')]
    public function show()
    {
        $projectId = $_GET['project'] ?? $_GET['projectId'] ?? '';
        $slug = trim($_GET['slug'] ?? '');
        if (empty($projectId) || empty($slug) || !isset($this->config['projects'][$projectId])) {
            Response::json(['error' => 'Project and slug parameters are required.'], 400);
            return;
        }

        $project = $this->config['projects'][$projectId];
        $version = $_GET['version'] ?? ($project['default_version'] ?? 'v1');
        $format = $_GET['format'] ?? 'html'; // html | markdown

        $docsDir = $this->resolveDocsDir($projectId, $version);
        $filePath = $docsDir . '/' . $slug . '.md';

        if (!file_exists($filePath)) {
            // Check blog
            if (!empty($project['blog_dir'])) {
                $blogDir = $project['blog_dir'];
                if (strpos($blogDir, 'C:/') === 0 || strpos($blogDir, 'c:/') === 0) {
                    $blogDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $blogDir);
                }
                if (!str_starts_with($blogDir, '/') && !str_contains($blogDir, ':\\')) {
                    $blogDir = dirname(SPP_BASE_DIR) . '/' . $blogDir;
                }
                $filePath = $blogDir . '/' . $slug . '.md';
            }
        }

        if (!file_exists($filePath)) {
            Response::json(['error' => 'Document not found.'], 404);
            return;
        }

        $raw = @file_get_contents($filePath) ?: '';
        $frontmatter = $this->extractFrontmatter($raw);

        // Check status
        $status = $frontmatter['status'] ?? 'published';
        $token = $_GET['token'] ?? '';
        $isValidToken = !empty($token) && \App\SPPDocs\Services\PermissionManager::verifyPreviewToken($projectId, 'doc', $slug, $token);

        if ($status === 'draft' && !$isValidToken) {
            Response::json(['error' => 'Document is in draft mode. A valid preview token is required.'], 403);
            return;
        }

        $bodyMarkdown = preg_replace('/^---\r?\n.*?\r?\n---\r?\n/s', '', $raw);

        // Expand snippets
        $bodyMarkdown = \App\SPPDocs\Services\SnippetManager::expand($projectId, $bodyMarkdown);

        $html = \App\SPPDocs\Services\IssueMarkdownService::render($bodyMarkdown, $projectId);

        // Extract headings for Table of Contents
        $headings = [];
        if (preg_match_all('/<h([2-4])(?:\s+id=["\']([^"\']+)["\'])?[^>]*>(.*?)<\/h\1>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $level = (int)$m[1];
                $titleText = strip_tags($m[3]);
                $id = !empty($m[2]) ? $m[2] : strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $titleText), '-'));
                $headings[] = [
                    'level' => $level,
                    'id' => $id,
                    'text' => $titleText,
                ];
            }
        }

        $user = $_SESSION['sppdocs_user'] ?? $_SESSION['user'] ?? null;
        $sanitizedFm = \App\SPPDocs\Services\SchemaStudioService::filterFieldsForUser($projectId, 'page', $frontmatter, $user);

        $payload = [
            'project' => $projectId,
            'slug' => $slug,
            'title' => $sanitizedFm['title'] ?? ucfirst(str_replace('-', ' ', basename($slug))),
            'frontmatter' => $sanitizedFm,
            'content' => ($format === 'markdown') ? $bodyMarkdown : $html,
            'headings' => $headings,
            'updated_at' => !empty($frontmatter['updated']) ? date('c', strtotime($frontmatter['updated'])) : date('c', filemtime($filePath)),
        ];

        $etag = '"' . md5(json_encode($payload)) . '"';
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }

        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=300');
        Response::json($payload);
    }

    private function extractFrontmatter(string $content): array
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n(.*)$/s', $content, $matches)) {
            try {
                return \Symfony\Component\Yaml\Yaml::parse($matches[1]) ?: [];
            } catch (\Exception $e) {
                return [];
            }
        }
        return [];
    }

    private function resolveDocsDir(string $projectId, string $version): string
    {
        $project = $this->config['projects'][$projectId] ?? [];
        $docsDir = $project['versions'][$version] ?? ($project['docs_dir'] ?? ('docs/' . $projectId . '/' . $version));
        if (strpos($docsDir, 'C:/') === 0 || strpos($docsDir, 'c:/') === 0) {
            $docsDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $docsDir);
        }
        if (!str_starts_with($docsDir, '/') && !str_contains($docsDir, ':\\')) {
            $docsDir = dirname(SPP_BASE_DIR) . '/' . $docsDir;
        }
        return $docsDir;
    }
}