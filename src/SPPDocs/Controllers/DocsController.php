<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Yaml\Yaml;

class DocsController extends \SPPMod\SPPView\ViewController
{
    private MarkdownConverter $converter;
    private array $config;

    public function __construct()
    {
        try {
            // Setup CommonMark
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new TableExtension());
            $environment->addExtension(new HeadingPermalinkExtension());
            $environment->addExtension(new FrontMatterExtension());
            
            // Setup the converter
            $this->converter = new MarkdownConverter($environment);

            // Load Configuration
            $configFile = __DIR__ . '/../etc/sppdocs.yml';
            $this->config = ['projects' => []];
            
            if (file_exists($configFile)) {
                $mainConfig = Yaml::parseFile($configFile);
                if (isset($mainConfig['projects']) && is_array($mainConfig['projects'])) {
                    foreach ($mainConfig['projects'] as $id => $projectConfigPath) {
                        // Replace hardcoded windows paths with dynamic relative paths based on SPP_BASE_DIR
                        if (strpos($projectConfigPath, 'C:/') === 0 || strpos($projectConfigPath, 'c:/') === 0) {
                            $projectConfigPath = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $projectConfigPath);
                        }
                        if (!str_starts_with($projectConfigPath, '/') && !str_contains($projectConfigPath, ':\\')) {
                            $projectConfigPath = dirname(SPP_BASE_DIR) . '/' . $projectConfigPath;
                        }
                        
                        if (file_exists($projectConfigPath)) {
                            $this->config['projects'][$id] = Yaml::parseFile($projectConfigPath);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            die("Error in DocsController construct: " . $e->getMessage());
        }
    }

    #[Route('/', method: 'GET')]
    public function index()
    {
        return $this->render('portal', ['projects' => $this->config['projects'] ?? []]);
    }

    public function renderDocPartial(string $view, array $data = []): string
    {
        return $this->renderPartial($view, $data);
    }

    #[Route('/project', method: 'GET')]
    #[Route('/project/{projectId}', method: 'GET')]
    public function project($projectId = null, $pageName = null)
    {
        return $this->projectHome($projectId, $pageName);
    }

    #[Route('/project', method: 'GET')]
    public function projectHome($projectId = null, $pageName = null, bool $isPreview = false)
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $projectId = $projectId ?? $_GET['projectId'] ?? $_GET['project'] ?? null;
        if ($projectId !== null) {
            $projectId = trim($projectId, " \t\n\r\0\x0B/");
            if ($projectId === '') {
                $projectId = null;
            }
        }

        // If no project ID is provided in URL, gracefully resolve active or default project
        if (!$projectId) {
            $sessionProj = $_SESSION['sppdocs_current_project'] ?? null;
            if ($sessionProj && isset($this->config['projects'][$sessionProj])) {
                $projectId = $sessionProj;
            } elseif (!empty($this->config['projects'])) {
                $defaultId = \SPP\SPPConfig::get('sppdocs.default_project') ?? 'demo-app';
                if (!isset($this->config['projects'][$defaultId])) {
                    $defaultId = array_key_first($this->config['projects']);
                }
                $projectId = $defaultId;
            }
        }

        $pageName = $pageName ?? $_GET['page'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $_SESSION['sppdocs_current_project'] = $projectId;
        
        $project = $this->config['projects'][$projectId];
        $authMode = \SPP\SPPConfig::get('sppdocs.auth_mode') ?? 'flatfile';
        $isAdmin = false;
        $userRole = 'guest';

        if ($authMode === 'sppauth') {
            if (class_exists('\SPP\SPPAuth') && \SPP\SPPAuth::isLoggedIn()) {
                $userRole = \SPP\SPPAuth::hasRight('sppdocs_admin') ? 'admin' : 'user';
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (isset($_SESSION['sppdocs_admin_auth'])) {
                $userRole = $_SESSION['sppdocs_role'] ?? 'admin';
            }
        }

        $allowedWriteRoles = $project['acl']['write'] ?? ['admin', 'editor'];
        if (in_array('*', $allowedWriteRoles) || in_array($userRole, $allowedWriteRoles)) {
            $isAdmin = true;
        }

        if ($pageName) {
            // Collision protection
            if (in_array($pageName, ['docs', 'blog'])) {
                return $this->render('errors/404', ['message' => 'Reserved route']);
            }

            if (!isset($project['pages_dir'])) {
                return $this->render('errors/404', ['message' => 'Pages directory not configured for this project']);
            }

            $pagesDir = $project['pages_dir'];
            $safePageName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $pageName);
            
            // Resolve relative paths in case it's windows
            if (strpos($pagesDir, 'C:/') === 0 || strpos($pagesDir, 'c:/') === 0) {
                $pagesDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $pagesDir);
            }
            if (!str_starts_with($pagesDir, '/') && !str_contains($pagesDir, ':\\')) {
                $pagesDir = dirname(SPP_BASE_DIR) . '/' . $pagesDir;
            }

            $fullPath = $pagesDir . '/' . $safePageName . '.md';

            if (!file_exists($fullPath)) {
                if ($isAdmin) {
                    $createUrl = \SPP\App::getBaseUrl() . '/admin/editor?project=' . $projectId . '&type=page&file=' . $safePageName;
                    $html = $this->renderPartial('partials/doc_not_found.blade.php', ['create_url' => $createUrl]);
                    return $this->render('docs', [
                        'title' => 'Page Not Found',
                        'content' => $html,
                        'project' => $project,
                        'project_id' => $projectId,
                        'version' => $project['default_version'] ?? 'v1'
                    ]);
                }
                return $this->render('errors/404', ['message' => 'Page not found']);
            }

            $result = $this->getRenderedContent($fullPath, $projectId);
            $frontmatter = $result['frontmatter'];
            $html = $result['html'];

            $user = $_SESSION['sppdocs_user'] ?? $_SESSION['user'] ?? null;
            $frontmatter = \App\SPPDocs\Services\SchemaStudioService::filterFieldsForUser($projectId, 'page', $frontmatter, $user);

            $hasValidToken = $isPreview || (!empty($_GET['token']) && \App\SPPDocs\Services\PermissionManager::verifyPreviewToken($projectId, 'page', $safePageName, $_GET['token']));
            if ($hasValidToken) {
                $isPreview = true;
            }

            if (!$isAdmin && !$isPreview) {
                if (($frontmatter['status'] ?? 'published') === 'draft') {
                    $notice = $this->renderPartial('partials/doc_draft_notice.blade.php', ['status' => 'draft']);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc-content.blade.php', ['title' => 'Draft', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
                if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                    $notice = $this->renderPartial('partials/doc_draft_notice.blade.php', ['status' => 'scheduled', 'date' => $frontmatter['date']]);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc-content.blade.php', ['title' => 'Scheduled', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
            }

            $title = $frontmatter['title'] ?? ucfirst(str_replace('-', ' ', $safePageName));
            
            if ($isAdmin) {
                $editUrl = \SPP\App::getBaseUrl() . '/admin/editor?project=' . $projectId . '&type=page&file=' . $safePageName;
                $historyUrl = \SPP\App::getBaseUrl() . '/admin/history?project=' . $projectId . '&type=page&file=' . $safePageName;
                $html = $this->renderPartial('partials/doc_admin_bar.blade.php', ['history_url' => $historyUrl, 'edit_url' => $editUrl]) . $html;
            } elseif ($isPreview) {
                $banner = $this->renderPartial('partials/preview_banner.blade.php', ['title' => $title, 'frontmatter' => $frontmatter]);
                $html = $banner . $html;
            }

            $data = [
                'title' => $title,
                'content' => $html,
                'project_id' => $projectId,
                'project' => $project,
                'frontmatter' => $frontmatter,
                'version' => $project['default_version'] ?? 'v1',
                'current_path' => $safePageName
            ];
            
            \App\SPPDocs\Services\ModuleManager::invokeAlter($projectId, 'document_render', $data);

            if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                return $this->renderPartial('partials/doc-content.blade.php', $data);
            }
            $tpl = \App\SPPDocs\Services\ThemeManager::resolveTemplate($projectId, $safePageName, 'page');
            return $this->render($tpl, $data);
        }
        
        $view = $project['landing_view'] ?? 'project.home';
        
        return $this->render($view, [
            'project' => $project,
            'project_id' => $projectId
        ]);
    }

    #[Route('/blog', method: 'GET')]
    public function blogHome($projectId = null, $postSlug = null, bool $isPreview = false)
    {
        $projectId = $projectId ?? $_GET['projectId'] ?? $_GET['project'] ?? null;
        $postSlug = $postSlug ?? $_GET['post'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }
        
        $project = $this->config['projects'][$projectId];

        if (!isset($project['blog_dir'])) {
            return $this->render('errors/404', ['message' => 'Blog directory not configured for this project']);
        }

        $blogDir = $project['blog_dir'];
        if (strpos($blogDir, 'C:/') === 0 || strpos($blogDir, 'c:/') === 0) {
            $blogDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $blogDir);
        }
        if (!str_starts_with($blogDir, '/') && !str_contains($blogDir, ':\\')) {
            $blogDir = dirname(SPP_BASE_DIR) . '/' . $blogDir;
        }

        if ($postSlug) {
            // Render specific post
            $safePostSlug = str_replace(['../', '..\\'], '', $postSlug);
            $fullPath = $blogDir . '/' . $safePostSlug . '.md';

            if (!file_exists($fullPath)) {
                return $this->render('errors/404', ['message' => 'Post not found']);
            }

            $result = $this->getRenderedContent($fullPath, $projectId);
            $frontmatter = $result['frontmatter'];
            $html = $result['html'];

            $user = $_SESSION['sppdocs_user'] ?? $_SESSION['user'] ?? null;
            $frontmatter = \App\SPPDocs\Services\SchemaStudioService::filterFieldsForUser($projectId, 'blog', $frontmatter, $user);

            $hasValidToken = $isPreview || (!empty($_GET['token']) && \App\SPPDocs\Services\PermissionManager::verifyPreviewToken($projectId, 'blog', $safePostSlug, $_GET['token']));
            if ($hasValidToken) {
                $isPreview = true;
            }

            $isAdmin = isset($_SESSION['sppdocs_admin_auth']);
            if (!$isAdmin && !$isPreview) {
                if (($frontmatter['status'] ?? 'published') === 'draft') {
                    $notice = $this->renderPartial('partials/doc_draft_notice.blade.php', ['status' => 'draft']);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc-content.blade.php', ['title' => 'Draft', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Post not found']);
                }
                if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                    $notice = $this->renderPartial('partials/doc_draft_notice.blade.php', ['status' => 'scheduled', 'date' => $frontmatter['date']]);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc-content.blade.php', ['title' => 'Scheduled', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Post not found']);
                }
            }

            $title = $frontmatter['title'] ?? ucwords(str_replace(['-', '_'], ' ', $postSlug));
            
            if ($isPreview) {
                $banner = $this->renderPartial('partials/preview_banner.blade.php', ['title' => $title, 'frontmatter' => $frontmatter]);
                $html = $banner . $html;
            }
            
            $data = [
                'title' => $title,
                'content' => $html,
                'project_id' => $projectId,
                'project' => $project,
                'frontmatter' => $frontmatter,
                'version' => $project['default_version'] ?? 'v1',
                'current_path' => $safePostSlug
            ];
            
            \App\SPPDocs\Services\ModuleManager::invokeAlter($projectId, 'document_render', $data);

            if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                return $this->renderPartial('partials/doc-content.blade.php', $data);
            }
            $tpl = \App\SPPDocs\Services\ThemeManager::resolveTemplate($projectId, $safePostSlug, 'blog');
            return $this->render($tpl, $data);
        }

        // Render index of posts
        $posts = [];
        if (is_dir($blogDir)) {
            $files = glob($blogDir . '/*.md');
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $result = $this->converter->convert($content);
                $fm = [];
                if ($result instanceof \League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter) {
                    $fm = $result->getFrontMatter();
                }
                
                $slug = basename($file, '.md');
                $posts[] = [
                    'slug' => $slug,
                    'title' => $fm['title'] ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
                    'date' => $fm['date'] ?? filemtime($file),
                    'author' => $fm['author'] ?? 'Admin',
                    'excerpt' => $fm['excerpt'] ?? substr(strip_tags($result->getContent()), 0, 150) . '...'
                ];
            }
            
            // Sort by date descending
            usort($posts, function($a, $b) {
                $dateA = is_numeric($a['date']) ? $a['date'] : strtotime($a['date']);
                $dateB = is_numeric($b['date']) ? $b['date'] : strtotime($b['date']);
                return $dateB <=> $dateA;
            });
        }
        
        return $this->render('blog.index', [
            'project' => $project,
            'project_id' => $projectId,
            'posts' => $posts
        ]);
    }

    #[Route('/preview', method: 'GET')]
    public function previewDraft()
    {
        $projectId = $_GET['project'] ?? $_GET['projectId'] ?? '';
        $type = $_GET['type'] ?? 'doc';
        $file = $_GET['file'] ?? $_GET['slug'] ?? $_GET['path'] ?? '';
        $token = $_GET['token'] ?? '';

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        if (!\App\SPPDocs\Services\PermissionManager::verifyPreviewToken($projectId, $type, $file, $token)) {
            http_response_code(403);
            return $this->render('errors/403', ['message' => 'Invalid or expired preview link.']);
        }

        if ($type === 'page') {
            return $this->projectHome($projectId, $file, true);
        } elseif ($type === 'blog') {
            return $this->blogHome($projectId, $file, true);
        } else {
            return $this->showDoc($projectId, null, null, $file, true);
        }
    }

    #[Route('/docs', method: 'GET')]
    public function showDoc($projectId = null, $version = null, $folder = null, $path = null, bool $isPreview = false)
    {
        $projectId = $projectId ?? $_GET['projectId'] ?? $_GET['project'] ?? null;
        $path = $path ?? $_GET['path'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $project = $this->config['projects'][$projectId];
        
        // Handle missing positional arguments
        if ($version === null) {
            $version = $project['default_version'] ?? null;
        }
        
        if (!isset($project['versions'][$version])) {
            return $this->render('errors/404', ['message' => 'Version not found for this project']);
        }

        $docsPath = $project['versions'][$version];
        
        // Resolve path dynamically since Apache is running in WSL!
        if (strpos($docsPath, 'C:/') === 0 || strpos($docsPath, 'c:/') === 0) {
            // Replace hardcoded windows paths with dynamic relative paths based on SPP_BASE_DIR
            $docsPath = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $docsPath);
        }
        if (!str_starts_with($docsPath, '/') && !str_contains($docsPath, ':\\')) {
            $docsPath = dirname(SPP_BASE_DIR) . '/' . $docsPath;
        }

        // Combine folder and path
        if ($path === null) {
            $path = $folder ?: 'index';
            $folder = null;
        }

        try {
            // Sanitize the path to prevent directory traversal
            $path = str_replace(['../', '..\\'], '', $path);
            if ($folder) {
                $folder = str_replace(['../', '..\\'], '', $folder);
                $path = $folder . '/' . $path;
            }
            if (empty($path)) {
                $path = 'index';
            }

            $fullPath = $docsPath . '/' . $path . '.md';

            if (!file_exists($fullPath)) {
                // Fallback to README if index was implicitly requested but missing
                if ($path === 'index' && file_exists($docsPath . '/README.md')) {
                    $fullPath = $docsPath . '/README.md';
                } else {
                    return $this->render('errors/404', ['message' => 'Documentation page not found at ' . $path]);
                }
            }

            $result = $this->getRenderedContent($fullPath, $projectId);
            $frontmatter = $result['frontmatter'];
            $html = $result['html'];

            $hasValidToken = $isPreview || (!empty($_GET['token']) && \App\SPPDocs\Services\PermissionManager::verifyPreviewToken($projectId, 'doc', $path, $_GET['token']));
            if ($hasValidToken) {
                $isPreview = true;
            }

            $isAdmin = isset($_SESSION['sppdocs_admin_auth']);
            if (!$isAdmin && !$isPreview) {
                if (($frontmatter['status'] ?? 'published') === 'draft') {
                    $notice = $this->renderPartial('partials/doc_draft_notice.blade.php', ['status' => 'draft']);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc-content.blade.php', ['title' => 'Draft', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
                if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                    $notice = $this->renderPartial('partials/doc_draft_notice.blade.php', ['status' => 'scheduled', 'date' => $frontmatter['date']]);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc-content.blade.php', ['title' => 'Scheduled', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
            }

            // Extract a clean title
            $titlePath = $folder ? basename($path) : $path;
            $titlePath = $frontmatter['title'] ?? str_replace(['-', '_'], ' ', $titlePath);

            if ($isPreview) {
                $banner = $this->renderPartial('partials/preview_banner.blade.php', ['title' => $titlePath, 'frontmatter' => $frontmatter]);
                $html = $banner . $html;
            }

            $data = [
                'title' => $project['title'] . ' - ' . ucwords($titlePath),
                'content' => $html,
                'project_id' => $projectId,
                'version' => $version,
                'project' => $project,
                'current_path' => $path
            ];

            \App\SPPDocs\Services\ModuleManager::invokeAlter($projectId, 'document_render', $data);

            // Content Negotiation for SPP
            if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                return $this->renderPartial('partials/doc-content.blade.php', $data);
            }

            $tpl = \App\SPPDocs\Services\ThemeManager::resolveTemplate($projectId, $path, 'doc');
            return $this->render($tpl, $data);
        } catch (\Throwable $e) {
            die("Error in DocsController: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        }
    }

    #[Route('/search', method: 'GET')]
    public function search()
    {
        $query = strtolower(trim($_GET['query'] ?? ''));
        $projectId = $_GET['project'] ?? '';
        
        if (strlen($query) < 3 || !$projectId) {
            return $this->renderPartial('partials/search-results.blade.php', ['results' => []]);
        }
        
        $cacheFile = SPP_BASE_DIR . "/var/cache/SPPDocs/search_{$projectId}.json";
        if (!file_exists($cacheFile)) {
            return $this->renderPartial('partials/search-results.blade.php', ['results' => []]);
        }
        
        $index = json_decode(file_get_contents($cacheFile), true);
        
        $results = [];
        foreach ($index as $word => $urls) {
            if (strpos($word, $query) !== false) {
                foreach ($urls as $urlInfo) {
                    $results[$urlInfo['url']] = $urlInfo; // Deduplicate by URL
                }
            }
        }
        
        return $this->renderPartial('partials/search-results.blade.php', ['results' => array_values($results)]);
    }

    #[Route('/blog/feed.xml', method: 'GET')]
    public function blogFeed($projectId = null)
    {
        $projectId = $projectId ?? $_GET['projectId'] ?? $_GET['project'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            return 'Project not found';
        }
        
        $project = $this->config['projects'][$projectId];
        if (!isset($project['blog_dir'])) {
            http_response_code(404);
            return 'Blog directory not configured for this project';
        }

        $blogDir = $project['blog_dir'];
        if (strpos($blogDir, 'C:/') === 0 || strpos($blogDir, 'c:/') === 0) {
            $blogDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $blogDir);
        }
        if (!str_starts_with($blogDir, '/') && !str_contains($blogDir, ':\\')) {
            $blogDir = dirname(SPP_BASE_DIR) . '/' . $blogDir;
        }

        $posts = [];
        if (is_dir($blogDir)) {
            $files = glob($blogDir . '/*.md');
            foreach ($files as $file) {
                $result = $this->getRenderedContent($file);
                $fm = $result['frontmatter'];
                if (($fm['status'] ?? 'published') === 'draft') continue;
                if (!empty($fm['date']) && strtotime($fm['date']) > time()) continue;
                
                $slug = basename($file, '.md');
                $posts[] = [
                    'slug' => $slug,
                    'title' => $fm['title'] ?? ucwords(str_replace(['-', '_'], ' ', $slug)),
                    'date' => $fm['date'] ?? date('r', filemtime($file)),
                    'author' => $fm['author'] ?? 'Admin',
                    'excerpt' => $fm['excerpt'] ?? substr(strip_tags($result['html']), 0, 200) . '...'
                ];
            }
            
            usort($posts, function($a, $b) {
                return strtotime($b['date']) <=> strtotime($a['date']);
            });
        }
        
        header('Content-Type: application/rss+xml; charset=utf-8');
        $baseUrl = \SPP\App::getBaseUrl();
        $feed = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
        $feed .= "<rss version=\"2.0\">\n<channel>\n";
        $feed .= "  <title>" . htmlspecialchars($project['name'] ?? 'SPPDocs Blog') . "</title>\n";
        $feed .= "  <link>" . htmlspecialchars($baseUrl . '/blog?project=' . $projectId) . "</link>\n";
        $feed .= "  <description>Recent updates from " . htmlspecialchars($project['name'] ?? 'SPPDocs') . "</description>\n";
        
        foreach ($posts as $post) {
            $postUrl = $baseUrl . '/blog?project=' . $projectId . '&post=' . urlencode($post['slug']);
            $feed .= "  <item>\n";
            $feed .= "    <title>" . htmlspecialchars($post['title']) . "</title>\n";
            $feed .= "    <link>" . htmlspecialchars($postUrl) . "</link>\n";
            $feed .= "    <description>" . htmlspecialchars($post['excerpt']) . "</description>\n";
            $feed .= "    <pubDate>" . date('r', strtotime($post['date'])) . "</pubDate>\n";
            $feed .= "    <author>" . htmlspecialchars($post['author']) . "</author>\n";
            $feed .= "  </item>\n";
        }
        $feed .= "</channel>\n</rss>";
        
        echo $feed;
        exit;
    }

    private function getRenderedContent(string $fullPath, string $projectId = ''): array
    {
        $cacheDir = SPP_BASE_DIR . '/cache/sppdocs';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        
        $mtime = filemtime($fullPath);
        $hash = md5($fullPath . '_' . $projectId);
        $cacheFile = $cacheDir . '/' . $hash . '_' . $mtime . '.cache';
        
        if (file_exists($cacheFile)) {
            return unserialize(file_get_contents($cacheFile));
        }
        
        // Clean up old cache versions for this file
        foreach (glob($cacheDir . '/' . $hash . '_*.cache') as $oldCache) {
            @unlink($oldCache);
        }
        
        $markdown = file_get_contents($fullPath);
        if ($projectId) {
            $projectVars = $this->config['projects'][$projectId]['variables'] ?? [];
            $markdown = \App\SPPDocs\Services\SnippetManager::expand($projectId, $markdown, $projectVars);
        }
        $markdownWithTokens = \App\SPPDocs\Services\MarkdownComponentEngine::extractPlaceholders($markdown, $this);
        $doc = $this->converter->convert($markdownWithTokens);
        
        $frontmatter = [];
        if ($doc instanceof \League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter) {
            $frontmatter = $doc->getFrontMatter();
        }
        
        $html = $doc->getContent();
        $html = \App\SPPDocs\Services\MarkdownComponentEngine::restorePlaceholders($html);

        // Process GitHub-Flavored Markdown Alert Callouts (> [!NOTE], > [!TIP], > [!IMPORTANT], > [!WARNING], > [!CAUTION])
        $alerts = [
            'NOTE' => ['color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.08)', 'icon' => 'ℹ️'],
            'TIP' => ['color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.08)', 'icon' => '💡'],
            'IMPORTANT' => ['color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.08)', 'icon' => '📌'],
            'WARNING' => ['color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.08)', 'icon' => '⚠️'],
            'CAUTION' => ['color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.08)', 'icon' => '🛑'],
        ];

        foreach ($alerts as $type => $cfg) {
            $pattern = '/<blockquote>\s*<p>\s*\[!' . $type . '\]\s*(?:<br\s*\/?>)?(.*?)(?:<\/p>)?\s*<\/blockquote>/is';
            $replacement = '<div class="alert-box alert-' . strtolower($type) . '" style="border-left: 4px solid ' . $cfg['color'] . '; background: ' . $cfg['bg'] . '; padding: 0.85rem 1.25rem; border-radius: 0 8px 8px 0; margin: 1.5rem 0;">'
                         . '<div style="font-weight: 700; color: ' . $cfg['color'] . '; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem;">' . $cfg['icon'] . ' ' . $type . '</div>'
                         . '<div style="color: var(--vp-c-text-2); font-size: 0.95rem; line-height: 1.6;">$1</div>'
                         . '</div>';
            $html = preg_replace($pattern, $replacement, $html);
        }

        $result = [
            'html' => $html,
            'frontmatter' => $frontmatter
        ];
        
        @file_put_contents($cacheFile, serialize($result));
        return $result;
    }
}




