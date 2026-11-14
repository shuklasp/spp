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
    use \App\SPPDocs\Traits\ProjectAwareTrait;

    private MarkdownConverter $converter;

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

            // Load Configuration via ProjectAwareTrait
            $this->loadProjectConfig();
        } catch (\Throwable $e) {
            die("Error in DocsController construct: " . $e->getMessage());
        }
    }

    #[Route('/', method: 'GET')]
    public function index()
    {
        $this->loadProjectConfig();
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;
        $isGlobalAdmin = $username ? \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($username) : false;

        $filter = strtolower($_GET['filter'] ?? 'all'); // 'all', 'apps', 'modules', 'docs', 'my', 'archived'
        $search = trim($_GET['search'] ?? '');

        $visibleProjects = [];
        foreach ($this->config['projects'] as $id => $proj) {
            // Check visibility using canUserViewProject
            if (!$this->canUserViewProject($id, $proj, $username)) {
                continue;
            }

            $isArchived = !empty($proj['is_archived']) || ($proj['status'] ?? '') === 'archived';
            $isSuspended = ($proj['status'] ?? '') === 'suspended';

            // Archived projects are only visible when filtering by 'archived' or 'my'
            if ($isArchived && $filter !== 'archived' && $filter !== 'my') {
                continue;
            }

            // Type filter
            $type = strtolower($proj['type'] ?? 'docs');
            if ($filter === 'apps' && $type !== 'app') {
                continue;
            }
            if ($filter === 'modules' && $type !== 'module') {
                continue;
            }
            if ($filter === 'docs' && $type !== 'docs') {
                continue;
            }
            if ($filter === 'my') {
                $isCreator = $username && strcasecmp($proj['creator'] ?? '', $username) === 0;
                $isMember = false;
                if ($username && isset($proj['members'][$username])) {
                    $isMember = true;
                }
                if ($username && !empty($proj['team'])) {
                    foreach ((array)$proj['team'] as $m) {
                        if (is_string($m) && strcasecmp($m, $username) === 0) $isMember = true;
                        if (is_array($m) && strcasecmp($m['user'] ?? ($m['username'] ?? ''), $username) === 0) $isMember = true;
                    }
                }
                if (!$isCreator && !$isMember) {
                    continue;
                }
            }

            // Search filter
            if ($search !== '') {
                $haystack = ($proj['title'] ?? '') . ' ' . ($proj['description'] ?? '') . ' ' . ($proj['motto'] ?? '') . ' ' . $id;
                if (stripos($haystack, $search) === false) {
                    continue;
                }
            }

            $visibleProjects[$id] = $proj;
        }

        return $this->render('portal', [
            'projects' => $visibleProjects,
            'currentUser' => $username,
            'isGlobalAdmin' => $isGlobalAdmin,
            'activeFilter' => $filter,
            'searchQuery' => $search,
            'totalCount' => count($this->config['projects']),
            'visibleCount' => count($visibleProjects),
        ]);
    }

    public function renderDocPartial(string $view, array $data = []): string
    {
        return $this->renderPartial($view, $data);
    }

    #[Route('/project', method: 'GET')]
    #[Route('/project/{projectId}', method: 'GET')]
    #[Route('/project/{projectId}/{pageName}', method: 'GET')]
    #[Route('/project/{projectId}/{folder}/{pageName}', method: 'GET')]
    public function project($projectId = null, $pageName = null, $folder = null)
    {
        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? null));
        $folder = $folder ?? ($this->routeParams['folder'] ?? null);
        $pageName = $pageName ?? ($this->routeParams['pageName'] ?? ($this->routeParams['page'] ?? null));
        return $this->projectHome($projectId, $pageName, false, $folder);
    }

    public function show($project = null, $version = null, $path = null)
    {
        $project = $project ?? ($this->routeParams['project'] ?? ($this->routeParams['projectId'] ?? null));
        $version = $version ?? ($this->routeParams['version'] ?? null);
        $path = $path ?? ($this->routeParams['path'] ?? 'index');
        return $this->projectHome($project, $path);
    }

    #[Route('/project', method: 'GET')]
    public function projectHome($projectId = null, $pageName = null, bool $isPreview = false, $folder = null)
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? ($_GET['projectId'] ?? ($_GET['project'] ?? null))));
        if ($projectId !== null) {
            $projectId = trim($projectId, " \t\n\r\0\x0B/");
            if ($projectId === '') {
                $projectId = null;
            }
        }

        // Resolve project ID using findProjectId and resolveActiveProjectId
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : $this->resolveActiveProjectId();

        $folder = $folder ?? ($this->routeParams['folder'] ?? ($_GET['folder'] ?? null));
        $pageName = $pageName ?? ($this->routeParams['pageName'] ?? ($this->routeParams['page'] ?? ($_GET['page'] ?? null)));
        if (!$resolvedProjectId || !isset($this->config['projects'][$resolvedProjectId])) {
            http_response_code(404);
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $projectId = $resolvedProjectId;
        $_SESSION['sppdocs_current_project'] = $projectId;
        
        $project = $this->config['projects'][$projectId];
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;
        $isGlobalAdmin = $username ? \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($username) : false;

        // Visibility & Access Enforcement
        if (!$this->canUserViewProject($projectId, $project, $username)) {
            http_response_code(403);
            $visibility = strtolower($project['visibility'] ?? 'public');
            $status = strtolower($project['status'] ?? 'active');
            if ($status === 'suspended') {
                $reason = $project['moderation']['suspension_reason'] ?? 'Content suspended by moderation';
                return $this->render('errors/403', ['message' => 'Project suspended by SatyaLab moderation: ' . $reason]);
            }
            if ($visibility === 'personal') {
                return $this->render('errors/403', ['message' => 'This project is marked Personal and is only accessible by its creator.']);
            }
            if ($visibility === 'group' || $visibility === 'closed_group') {
                return $this->render('errors/403', ['message' => 'This is a closed group project. Only authorized team members can access it.']);
            }
            return $this->render('errors/403', ['message' => 'Access Denied: You do not have permission to view this project.']);
        }
        $authMode = \SPP\SPPConfig::get('sppdocs.auth_mode') ?? 'flatfile';
        $isAdmin = false;
        $userRole = 'guest';

        if ($authMode === 'sppauth') {
            if (class_exists('\SPP\SPPAuth') && \SPP\SPPAuth::isLoggedIn()) {
                $userRole = \SPP\SPPAuth::hasRight('sppdocs_admin') ? 'admin' : 'user';
            }
        } else {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
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

            $version = $this->routeParams['version'] ?? ($project['default_version'] ?? 'v1');
            $pagesDir = null;

            if ($folder) {
                if ($folder === 'tutorials') {
                    $pagesDir = dirname(SPP_BASE_DIR) . '/docs/tutorials';
                } elseif ($folder === 'commands') {
                    $pagesDir = dirname(SPP_BASE_DIR) . '/docs/commands';
                } elseif ($folder === 'courses') {
                    $pagesDir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/courses';
                } elseif ($folder === 'handbook') {
                    $pagesDir = dirname(SPP_BASE_DIR) . '/docs/handbook';
                } elseif ($folder === 'engine') {
                    $pagesDir = dirname(SPP_BASE_DIR) . '/src/SPPDocs/docs';
                } else {
                    $safeFolder = preg_replace('/[^a-zA-Z0-9\-_]/', '', $folder);
                    $baseDir = $project['versions'][$version] ?? ($project['pages_dir'] ?? null);
                    if ($baseDir) {
                        $pagesDir = $baseDir . '/' . $safeFolder;
                    }
                }
            } elseif (!empty($version) && isset($project['versions'][$version])) {
                $pagesDir = $project['versions'][$version];
            } elseif (isset($project['pages_dir'])) {
                $pagesDir = $project['pages_dir'];
            }

            if (!$pagesDir) {
                return $this->render('errors/404', ['message' => 'Pages directory not configured for this project']);
            }
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
                // 1. Fallback for 'index' to README.md or first sidebar item
                if (strtolower($safePageName) === 'index') {
                    if (file_exists($pagesDir . '/README.md')) {
                        $fullPath = $pagesDir . '/README.md';
                        $safePageName = 'README';
                    } elseif (file_exists($pagesDir . '/readme.md')) {
                        $fullPath = $pagesDir . '/readme.md';
                        $safePageName = 'readme';
                    } elseif (!empty($project['sidebar'])) {
                        foreach ($project['sidebar'] as $category => $links) {
                            if (is_array($links)) {
                                foreach ($links as $link) {
                                    if (is_array($link)) {
                                        $firstSlug = reset($link);
                                        if (!empty($firstSlug) && file_exists($pagesDir . '/' . $firstSlug . '.md')) {
                                            $fullPath = $pagesDir . '/' . $firstSlug . '.md';
                                            $safePageName = $firstSlug;
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // 2. Case-insensitive filename matching fallback
                if (!file_exists($fullPath) && is_dir($pagesDir)) {
                    $scan = @scandir($pagesDir);
                    if (is_array($scan)) {
                        foreach ($scan as $file) {
                            if (strcasecmp($file, $safePageName . '.md') === 0) {
                                $fullPath = $pagesDir . '/' . $file;
                                break;
                            }
                        }
                    }
                }
            }

            if (!file_exists($fullPath)) {
                if ($isAdmin) {
                    $createUrl = \SPP\App::getBaseUrl() . '/admin/editor?project=' . $projectId . '&type=page&file=' . $safePageName;
                    $html = $this->renderPartial('partials/doc_not_found', ['create_url' => $createUrl]);
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
                    $notice = $this->renderPartial('partials/doc_draft_notice', ['status' => 'draft']);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc_content', ['title' => 'Draft', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
                if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                    $notice = $this->renderPartial('partials/doc_draft_notice', ['status' => 'scheduled', 'date' => $frontmatter['date']]);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc_content', ['title' => 'Scheduled', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
            }

            $title = $frontmatter['title'] ?? ucfirst(str_replace('-', ' ', $safePageName));
            
            if ($isAdmin) {
                $editUrl = \SPP\App::getBaseUrl() . '/admin/editor?project=' . $projectId . '&type=page&file=' . $safePageName;
                $historyUrl = \SPP\App::getBaseUrl() . '/admin/history?project=' . $projectId . '&type=page&file=' . $safePageName;
                $html = $this->renderPartial('partials/doc_admin_bar', ['history_url' => $historyUrl, 'edit_url' => $editUrl]) . $html;
            } elseif ($isPreview) {
                $banner = $this->renderPartial('partials/preview_banner', ['title' => $title, 'frontmatter' => $frontmatter]);
                $html = $banner . $html;
            }

            $isBook = \App\SPPDocs\Services\DocNavigationService::isBook($project, $projectId, $frontmatter);
            $adjacentPages = $isBook 
                ? \App\SPPDocs\Services\DocNavigationService::getAdjacentPages($project, $safePageName, $projectId, $version, $frontmatter)
                : ['prev' => null, 'next' => null];

            $data = [
                'title' => $title,
                'content' => $html,
                'project_id' => $projectId,
                'project' => $project,
                'frontmatter' => $frontmatter,
                'version' => $version,
                'current_path' => $safePageName,
                'is_book' => $isBook,
                'prev_page' => $adjacentPages['prev'] ?? null,
                'next_page' => $adjacentPages['next'] ?? null,
            ];
            
            \App\SPPDocs\Services\ModuleManager::invokeAlter($projectId, 'document_render', $data);

            if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                return $this->renderPartial('partials/doc_content', $data);
            }
            $tpl = \App\SPPDocs\Services\ThemeManager::resolveTemplate($projectId, $safePageName, 'page');
            return $this->render($tpl, $data);
        }
        
        $view = $project['landing_view'] ?? 'project.home';
        $canManage = $this->canUserManageProject($projectId, $project, $username);
        $isArchived = !empty($project['is_archived']) || ($project['status'] ?? '') === 'archived';
        $isSuspended = ($project['status'] ?? '') === 'suspended';
        
        return $this->render($view, [
            'project' => $project,
            'project_id' => $projectId,
            'currentUser' => $username,
            'canManage' => $canManage,
            'isGlobalAdmin' => $isGlobalAdmin,
            'isArchived' => $isArchived,
            'isSuspended' => $isSuspended,
            'projectType' => strtolower($project['type'] ?? 'docs')
        ]);
    }

    public function blog($projectId = null)
    {
        $projectId = $projectId ?? ($this->routeParams['project'] ?? ($this->routeParams['projectId'] ?? ($this->routeParams['slug'] ?? null)));
        return $this->blogHome($projectId);
    }

    public function blogPost($project = null, $slug = null)
    {
        $project = $project ?? ($this->routeParams['project'] ?? ($this->routeParams['projectId'] ?? null));
        $slug = $slug ?? ($this->routeParams['slug'] ?? ($_GET['slug'] ?? ($_GET['post'] ?? null)));

        // Disambiguate single parameter routes (e.g. /blog/{slug}):
        if ($slug === null && $project !== null) {
            $matched = $this->findProjectId($project);
            if ($matched) {
                // It's a project blog home (e.g. /blog/demo app or /blog/demo-app)
                return $this->blogHome($matched, null);
            }
            // Otherwise, $project is actually a post slug for the active/default project
            $slug = $project;
            $project = null;
        }

        return $this->blogHome($project, $slug);
    }

    #[Route('/blog', method: 'GET')]
    #[Route('/blog/{projectId}', method: 'GET')]
    #[Route('/blog/{projectId}/{postSlug}', method: 'GET')]
    public function blogHome($projectId = null, $postSlug = null, bool $isPreview = false)
    {
        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? ($_GET['projectId'] ?? ($_GET['project'] ?? null))));
        $postSlug = $postSlug ?? ($this->routeParams['postSlug'] ?? ($this->routeParams['slug'] ?? ($_GET['post'] ?? ($_GET['slug'] ?? null))));

        if ($projectId !== null) {
            $projectId = trim($projectId, " \t\n\r\0\x0B/");
            if ($projectId === '') {
                $projectId = null;
            }
        }

        // 1. If $projectId is given, resolve canonical project ID
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : null;

        // 2. If no valid project found, check if $projectId is actually a post slug for the active project
        if (!$resolvedProjectId && $projectId && !$postSlug) {
            $activeProjId = $this->resolveActiveProjectId();
            if ($activeProjId) {
                $activeProj = $this->config['projects'][$activeProjId] ?? [];
                $testBlogDir = $activeProj['blog_dir'] ?? null;
                if ($testBlogDir) {
                    $normBlogDir = $testBlogDir;
                    if (strpos($normBlogDir, 'C:/') === 0 || strpos($normBlogDir, 'c:/') === 0) {
                        $normBlogDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $normBlogDir);
                    }
                    if (!str_starts_with($normBlogDir, '/') && !str_contains($normBlogDir, ':\\')) {
                        $normBlogDir = dirname(SPP_BASE_DIR) . '/' . $normBlogDir;
                    }
                    if (file_exists($normBlogDir . '/' . str_replace(['../', '..\\'], '', $projectId) . '.md')) {
                        $resolvedProjectId = $activeProjId;
                        $postSlug = $projectId;
                    }
                }
            }
        }

        // 3. Fallback to active project if no project was specified
        if (!$resolvedProjectId && !$projectId) {
            $resolvedProjectId = $this->resolveActiveProjectId();
        }

        if (!$resolvedProjectId || !isset($this->config['projects'][$resolvedProjectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }
        
        $projectId = $resolvedProjectId;
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['sppdocs_current_project'] = $projectId;

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
                    $notice = $this->renderPartial('partials/doc_draft_notice', ['status' => 'draft']);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc_content', ['title' => 'Draft', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Post not found']);
                }
                if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                    $notice = $this->renderPartial('partials/doc_draft_notice', ['status' => 'scheduled', 'date' => $frontmatter['date']]);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc_content', ['title' => 'Scheduled', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Post not found']);
                }
            }

            $title = $frontmatter['title'] ?? ucwords(str_replace(['-', '_'], ' ', $postSlug));

            $headerPartial = $this->renderPartial('partials/blog_post_header', [
                'project_id' => $projectId,
                'project' => $project,
                'title' => $title,
                'frontmatter' => $frontmatter,
                'slug' => $safePostSlug
            ]);
            $html = $headerPartial . $html;
            
            if ($isPreview) {
                $banner = $this->renderPartial('partials/preview_banner', ['title' => $title, 'frontmatter' => $frontmatter]);
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
                return $this->renderPartial('partials/doc_content', $data);
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
                    'category' => $fm['category'] ?? null,
                    'tags' => $fm['tags'] ?? null,
                    'excerpt' => $fm['excerpt'] ?? substr(strip_tags($result->getContent()), 0, 150) . '...'
                ];
            }
            
            // Sort by date descending
            usort($posts, function($a, $b) {
                $dateA = is_numeric($a['date']) ? $a['date'] : strtotime($a['date']);
                $dateB = is_numeric($b['date']) ? $b['date'] : strtotime($b['date']);
                return $dateB <=> $dateA;
            });

            // Optional tag or category filtering
            $filterTag = $_GET['tag'] ?? null;
            $filterCategory = $_GET['category'] ?? null;
            if ($filterTag) {
                $filterTagLower = strtolower(trim($filterTag));
                $posts = array_filter($posts, function($p) use ($filterTagLower) {
                    if (empty($p['tags'])) return false;
                    $pTags = is_array($p['tags']) ? $p['tags'] : explode(',', (string)$p['tags']);
                    foreach ($pTags as $t) {
                        if (strtolower(trim($t)) === $filterTagLower) return true;
                    }
                    return false;
                });
            }
            if ($filterCategory) {
                $filterCatLower = strtolower(trim($filterCategory));
                $posts = array_filter($posts, function($p) use ($filterCatLower) {
                    return !empty($p['category']) && strtolower(trim($p['category'])) === $filterCatLower;
                });
            }
        }
        
        return $this->render('blog.index', [
            'project' => $project,
            'project_id' => $projectId,
            'posts' => array_values($posts),
            'base_url' => \SPP\App::getBaseUrl(),
            'version' => $project['default_version'] ?? 'v1',
            'current_path' => 'blog'
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
        $projectId = $projectId ?? ($this->routeParams['project'] ?? ($this->routeParams['projectId'] ?? ($_GET['projectId'] ?? ($_GET['project'] ?? null))));
        $path = $path ?? ($this->routeParams['path'] ?? ($_GET['path'] ?? null));
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : $this->resolveActiveProjectId();
        if (!$resolvedProjectId || !isset($this->config['projects'][$resolvedProjectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $projectId = $resolvedProjectId;
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
                    $notice = $this->renderPartial('partials/doc_draft_notice', ['status' => 'draft']);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc_content', ['title' => 'Draft', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
                if (!empty($frontmatter['date']) && strtotime($frontmatter['date']) > time()) {
                    $notice = $this->renderPartial('partials/doc_draft_notice', ['status' => 'scheduled', 'date' => $frontmatter['date']]);
                    if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'doc-content') {
                        return $this->renderPartial('partials/doc_content', ['title' => 'Scheduled', 'content' => $notice]);
                    }
                    return $this->render('errors/404', ['message' => 'Page not found']);
                }
            }

            // Extract a clean title
            $titlePath = $folder ? basename($path) : $path;
            $titlePath = $frontmatter['title'] ?? str_replace(['-', '_'], ' ', $titlePath);

            if ($isPreview) {
                $banner = $this->renderPartial('partials/preview_banner', ['title' => $titlePath, 'frontmatter' => $frontmatter]);
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
                return $this->renderPartial('partials/doc_content', $data);
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
            return $this->renderPartial('partials/search_results', ['results' => []]);
        }
        
        $cacheFile = SPP_BASE_DIR . "/var/cache/SPPDocs/search_{$projectId}.json";
        if (!file_exists($cacheFile)) {
            return $this->renderPartial('partials/search_results', ['results' => []]);
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
        
        return $this->renderPartial('partials/search_results', ['results' => array_values($results)]);
    }

    #[Route('/blog/feed.xml', method: 'GET')]
    public function blogFeed($projectId = null)
    {
        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? ($_GET['projectId'] ?? ($_GET['project'] ?? null))));
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : $this->resolveActiveProjectId();
        if (!$resolvedProjectId || !isset($this->config['projects'][$resolvedProjectId])) {
            http_response_code(404);
            return 'Project not found';
        }
        
        $projectId = $resolvedProjectId;
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

    /**
     * Lists discussions for a project powered by e-Vaarta (ई-वार्ता).
     */
    #[Route('/project/{projectId}/discussions', method: 'GET')]
    public function discussions($projectId = null)
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? ($_GET['projectId'] ?? null)));
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : $this->resolveActiveProjectId();

        if (!$resolvedProjectId || !isset($this->config['projects'][$resolvedProjectId])) {
            http_response_code(404);
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $projectId = $resolvedProjectId;
        $_SESSION['sppdocs_current_project'] = $projectId;
        $project = $this->config['projects'][$projectId];

        if (!class_exists('\\SPPMod\\SPPMail\\InboxStore')) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 4) . '/spp';
            $mailInit = $baseDir . '/modules/optional/sppmail/modinit.php';
            if (file_exists($mailInit)) {
                require_once $mailInit;
            }
        }

        $threads = [];
        if (class_exists('\\SPPMod\\SPPMail\\InboxStore')) {
            try {
                $threads = \SPPMod\SPPMail\InboxStore::listThreads([
                    'entity_type' => 'project',
                    'entity_id' => $projectId,
                    'limit' => 50,
                ]);
            } catch (\Throwable $e) {
                $threads = [];
            }
        }

        $selectedThreadId = $_GET['thread'] ?? (!empty($threads[0]['id']) ? $threads[0]['id'] : null);
        $activeThread = null;
        if ($selectedThreadId && class_exists('\\SPPMod\\SPPMail\\InboxStore')) {
            try {
                $activeThread = \SPPMod\SPPMail\InboxStore::getThread($selectedThreadId);
            } catch (\Throwable $e) {}
        }

        $data = [
            'project' => $project,
            'project_id' => $projectId,
            'threads' => $threads,
            'active_thread' => $activeThread,
            'selected_thread_id' => $selectedThreadId
        ];

        if ($this->isHtmx() && isset($_SERVER['HTTP_HX_TARGET']) && $_SERVER['HTTP_HX_TARGET'] === 'discussion-threads-sidebar') {
            return $this->renderPartial('partials/evaarta_thread_list.blade.php', $data);
        }

        return $this->render('project.discussions', $data);
    }

    /**
     * Views a specific discussion thread.
     */
    #[Route('/project/{projectId}/discussions/{threadId}', method: 'GET')]
    public function viewDiscussion($projectId = null, $threadId = null)
    {
        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? null));
        $threadId = $threadId ?? ($this->routeParams['threadId'] ?? ($_GET['thread'] ?? null));
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : $this->resolveActiveProjectId();

        if (!$resolvedProjectId || !isset($this->config['projects'][$resolvedProjectId])) {
            http_response_code(404);
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        if (!class_exists('\\SPPMod\\SPPMail\\InboxStore')) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 4) . '/spp';
            $mailInit = $baseDir . '/modules/optional/sppmail/modinit.php';
            if (file_exists($mailInit)) {
                require_once $mailInit;
            }
        }

        $thread = null;
        if ($threadId && class_exists('\\SPPMod\\SPPMail\\InboxStore')) {
            try {
                $thread = \SPPMod\SPPMail\InboxStore::getThread($threadId);
            } catch (\Throwable $e) {}
        }

        if (!$thread) {
            http_response_code(404);
            return $this->render('errors/404', ['message' => 'Discussion thread not found']);
        }

        $data = [
            'project' => $this->config['projects'][$resolvedProjectId],
            'project_id' => $resolvedProjectId,
            'thread' => $thread
        ];

        if ($this->isHtmx()) {
            return $this->renderPartial('partials/evaarta_thread_view.blade.php', $data);
        }

        $_GET['thread'] = $threadId;
        return $this->discussions($resolvedProjectId);
    }

    /**
     * Creates a new project discussion topic.
     */
    #[Route('/project/{projectId}/discussions/new', method: 'POST')]
    public function createDiscussion($projectId = null)
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? null));
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : $this->resolveActiveProjectId();

        if (!$resolvedProjectId) {
            http_response_code(404);
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        if (!class_exists('\\SPPMod\\SPPMail\\InboxStore')) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 4) . '/spp';
            $mailInit = $baseDir . '/modules/optional/sppmail/modinit.php';
            if (file_exists($mailInit)) {
                require_once $mailInit;
            }
        }

        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $category = trim($_POST['category'] ?? 'general');

        if ($subject === '' || $body === '') {
            $this->redirect(\SPP\App::getBaseUrl() . '/project/' . $resolvedProjectId . '/discussions');
            return;
        }

        $user = $_SESSION['sppdocs_user'] ?? 'Stakeholder';
        $email = strtolower(preg_replace('/[^a-zA-Z0-9_\.]/', '', $user)) . '@spp.local';

        $threadId = 'th_proj_' . substr(hash('sha256', $resolvedProjectId . ':' . uniqid('', true)), 0, 12);
        
        \SPPMod\SPPMail\InboxStore::storeMessage([
            'thread_id' => $threadId,
            'subject' => $subject,
            'body_text' => $body,
            'from' => ['name' => $user, 'email' => $email],
            'received_at' => date('Y-m-d H:i:s'),
        ]);

        \SPPMod\SPPMail\InboxStore::saveThread([
            'id' => $threadId,
            'subject' => $subject,
            'snippet' => mb_substr($body, 0, 140),
            'category' => $category,
            'workflow_state' => 'open',
            'entity_type' => 'project',
            'entity_id' => $resolvedProjectId,
            'participants' => [['name' => $user, 'email' => $email]],
            'last_message_at' => date('Y-m-d H:i:s'),
        ]);

        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent('evaarta.thread_created', ['thread_id' => $threadId, 'project_id' => $resolvedProjectId]);
            \SPP\SPPEvent::triggerHook('evaarta:thread_created', ['thread_id' => $threadId, 'project_id' => $resolvedProjectId]);
        }

        $this->redirect(\SPP\App::getBaseUrl() . '/project/' . $resolvedProjectId . '/discussions/' . $threadId);
    }

    /**
     * Replies to a discussion or posts an internal team note.
     */
    #[Route('/project/{projectId}/discussions/{threadId}/reply', method: 'POST')]
    public function replyDiscussion($projectId = null, $threadId = null)
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $projectId = $projectId ?? ($this->routeParams['projectId'] ?? ($this->routeParams['project'] ?? null));
        $threadId = $threadId ?? ($this->routeParams['threadId'] ?? null);
        $resolvedProjectId = $projectId ? $this->findProjectId($projectId) : $this->resolveActiveProjectId();

        if (!class_exists('\\SPPMod\\SPPMail\\InboxStore')) {
            $baseDir = defined('SPP_BASE_DIR') ? SPP_BASE_DIR : dirname(__DIR__, 4) . '/spp';
            $mailInit = $baseDir . '/modules/optional/sppmail/modinit.php';
            if (file_exists($mailInit)) {
                require_once $mailInit;
            }
        }

        $body = trim($_POST['body'] ?? '');
        $isInternalNote = !empty($_POST['is_internal_note']);
        $user = $_SESSION['sppdocs_user'] ?? 'Team Member';
        $email = strtolower(preg_replace('/[^a-zA-Z0-9_\.]/', '', $user)) . '@spp.local';

        if ($body !== '' && $threadId) {
            if ($isInternalNote) {
                \SPPMod\SPPMail\InboxStore::addNote($threadId, $body, $user);
            } else {
                \SPPMod\SPPMail\InboxStore::storeMessage([
                    'thread_id' => $threadId,
                    'subject' => 'Re: Discussion',
                    'body_text' => $body,
                    'from' => ['name' => $user, 'email' => $email],
                    'received_at' => date('Y-m-d H:i:s'),
                ]);

                \SPPMod\SPPMail\InboxStore::saveThread([
                    'id' => $threadId,
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'snippet' => mb_substr($body, 0, 140)
                ]);
            }

            if (class_exists('\\SPP\\SPPEvent')) {
                \SPP\SPPEvent::fireEvent('evaarta.reply_posted', ['thread_id' => $threadId, 'is_note' => $isInternalNote]);
                \SPP\SPPEvent::triggerHook('evaarta:reply_posted', ['thread_id' => $threadId, 'is_note' => $isInternalNote]);
            }
        }

        $thread = \SPPMod\SPPMail\InboxStore::getThread($threadId);
        $data = [
            'project' => $this->config['projects'][$resolvedProjectId] ?? [],
            'project_id' => $resolvedProjectId,
            'thread' => $thread
        ];

        return $this->renderPartial('partials/evaarta_thread_view.blade.php', $data);
    }
}




