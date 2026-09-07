<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use Symfony\Component\Yaml\Yaml;
use App\SPPDocs\Services\FeatureManager;
use SPP\App;
use SPP\Response;

class ForumController extends \SPPMod\SPPView\ViewController
{
    private array $config;

    public function __construct()
    {
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        $this->config = ['projects' => []];
        
        if (file_exists($configFile)) {
            $mainConfig = Yaml::parseFile($configFile);
            if (isset($mainConfig['projects']) && is_array($mainConfig['projects'])) {
                foreach ($mainConfig['projects'] as $id => $projectConfigPath) {
                    if (strpos($projectConfigPath, 'C:/') === 0 || strpos($projectConfigPath, 'c:/') === 0) {
                        $projectConfigPath = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $projectConfigPath);
                    }
                    if (!str_starts_with($projectConfigPath, '/') && !str_contains($projectConfigPath, ':\\')) {
                        $projectConfigPath = dirname(SPP_BASE_DIR) . '/' . $projectConfigPath;
                    }
                    if (file_exists($projectConfigPath)) {
                        $this->config['projects'][$id] = Yaml::parseFile($projectConfigPath);
                        $this->config['projects'][$id]['_config_path'] = $projectConfigPath;
                    }
                }
            }
        }
    }

    private function getForumsDir($project)
    {
        $dir = $project['forums_dir'] ?? dirname($project['_config_path']) . '/forums';
        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = SPP_BASE_DIR . '/' . $dir;
        }
        return $dir;
    }

    private function getUser()
    {
        // 1. Check session-based auth (flatfile / compatibility)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['sppdocs_user'])) {
            return [
                'username' => $_SESSION['sppdocs_user'],
                'role' => $_SESSION['sppdocs_role'] ?? 'admin'
            ];
        }

        // 2. Check SPPAuth (module-based authentication)
        if (class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
            $guard = \SPPMod\SPPAuth\SPPAuth::guard('web');
            $user = $guard->user();
            return [
                'username' => $user->username ?? $user->name ?? 'User',
                'role' => 'registered'
            ];
        }

        return null; // Guest
    }

    private function checkAcl($forumConfig, $action, $user, $projectConfig = [])
    {
        $acl = $forumConfig['acl'] ?? ['read' => ['*'], 'write' => ['registered']];
        $allowed = $acl[$action] ?? ['admin'];
        
        $role = $user ? $user['role'] : 'guest';
        
        if ($action === 'write' && $role === 'guest' && !empty($projectConfig['forums_guest_posting'])) {
            return true;
        }

        if (in_array('*', $allowed)) return true;
        
        if (in_array($role, $allowed)) return true;
        
        if ($role === 'admin') return true; // Admins can always read/write
        
        return false;
    }

    #[Route('/forums', method: 'GET')]
    public function index($projectId = null)
    {
        $projectId = $projectId ?? $_GET['projectId'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }
        
        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('forums', $project, $projectId);

        $forumsDir = $this->getForumsDir($project);
        
        if (!is_dir($forumsDir)) {
            mkdir($forumsDir, 0755, true);
        }
        
        $forumsFile = $forumsDir . '/forums.yml';
        if (!file_exists($forumsFile)) {
            $defaultForums = [
                'general' => [
                    'title' => 'General Discussion',
                    'description' => 'Talk about anything related to ' . ($project['title'] ?? 'this project'),
                    'acl' => ['read' => ['*'], 'write' => ['registered']]
                ]
            ];
            file_put_contents($forumsFile, Yaml::dump($defaultForums, 4, 2));
        }
        
        $forumsConfig = Yaml::parseFile($forumsFile);
        $user = $this->getUser();
        
        $visibleForums = [];
        foreach ($forumsConfig as $slug => $fConfig) {
            if ($this->checkAcl($fConfig, 'read', $user)) {
                $fConfig['slug'] = $slug;
                
                // Count threads
                $fConfig['thread_count'] = 0;
                $threadDir = $forumsDir . '/' . $slug;
                if (is_dir($threadDir)) {
                    $fConfig['thread_count'] = count(glob($threadDir . '/*.json'));
                }
                
                $visibleForums[] = $fConfig;
            }
        }
        
        return $this->render('forum.index', [
            'project' => $project,
            'project_id' => $projectId,
            'forums' => $visibleForums,
            'user' => $user
        ]);
    }

    #[Route('/forums/forum', method: 'GET')]
    public function listThreads($projectId = null, $forumSlug = null)
    {
        $projectId = $projectId ?? $_GET['projectId'] ?? null;
        $forumSlug = $forumSlug ?? $_GET['forumSlug'] ?? $_GET['forum'] ?? null;
        if (!$projectId || !$forumSlug || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Not found']);
        }
        
        $project = $this->config['projects'][$projectId];
        $forumsDir = $this->getForumsDir($project);
        $forumsFile = $forumsDir . '/forums.yml';
        $forumsConfig = file_exists($forumsFile) ? Yaml::parseFile($forumsFile) : [];
        
        if (!isset($forumsConfig[$forumSlug])) {
            return $this->render('errors/404', ['message' => 'Forum not found']);
        }
        
        $fConfig = $forumsConfig[$forumSlug];
        $user = $this->getUser();
        
        if (!$this->checkAcl($fConfig, 'read', $user)) {
            return $this->render('errors/403', ['message' => 'Access denied']);
        }
        
        $threadDir = $forumsDir . '/' . $forumSlug;
        if (!is_dir($threadDir)) {
            mkdir($threadDir, 0755, true);
        }
        
        $threads = [];
        foreach (glob($threadDir . '/*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $data['id'] = basename($file, '.json');
                $data['reply_count'] = count($data['replies'] ?? []);
                $threads[] = $data;
            }
        }
        
        // Sort by last activity
        usort($threads, function($a, $b) {
            $lastA = !empty($a['replies']) ? end($a['replies'])['timestamp'] : $a['timestamp'];
            $lastB = !empty($b['replies']) ? end($b['replies'])['timestamp'] : $b['timestamp'];
            return $lastB <=> $lastA;
        });

        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['sppdocs_csrf'])) $_SESSION['sppdocs_csrf'] = bin2hex(random_bytes(32));

        return $this->render('forum.list', [
            'project' => $project,
            'project_id' => $projectId,
            'forum' => $fConfig,
            'slug' => $forumSlug,
            'threads' => $threads,
            'user' => $user,
            'can_post' => $this->checkAcl($fConfig, 'write', $user, $project),
            'csrf_token' => $_SESSION['sppdocs_csrf']
        ]);
    }
    
    #[Route('/forums/thread', method: 'GET')]
    public function viewThread($projectId = null, $forumSlug = null, $threadId = null)
    {
        $projectId = $projectId ?? $_GET['projectId'] ?? null;
        $forumSlug = $forumSlug ?? $_GET['forumSlug'] ?? $_GET['forum'] ?? null;
        $threadId = $threadId ?? $_GET['threadId'] ?? $_GET['thread'] ?? null;
        if (!$projectId || !$forumSlug || !$threadId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Not found']);
        }
        
        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('forums', $project, $projectId);

        $forumsDir = $this->getForumsDir($project);
        $forumsFile = $forumsDir . '/forums.yml';
        $forumsConfig = file_exists($forumsFile) ? Yaml::parseFile($forumsFile) : [];
        
        if (!isset($forumsConfig[$forumSlug])) return $this->render('errors/404', ['message' => 'Forum not found']);
        $fConfig = $forumsConfig[$forumSlug];
        
        $user = $this->getUser();
        if (!$this->checkAcl($fConfig, 'read', $user)) return $this->render('errors/403', ['message' => 'Access denied']);
        
        $threadFile = $forumsDir . '/' . $forumSlug . '/' . basename($threadId) . '.json';
        if (!file_exists($threadFile)) return $this->render('errors/404', ['message' => 'Thread not found']);
        
        $thread = json_decode(file_get_contents($threadFile), true);
        $thread['id'] = basename($threadId);

        if (session_status() === PHP_SESSION_NONE) session_start();
        $csrfToken = class_exists('\SPP\SPPSession') ? \SPP\SPPSession::getCsrfToken() : ($_SESSION['sppdocs_csrf'] ?? '');
        $_SESSION['sppdocs_csrf'] = $csrfToken;

        return $this->render('forum.thread', [
            'project' => $project,
            'project_id' => $projectId,
            'forum' => $fConfig,
            'slug' => $forumSlug,
            'thread' => $thread,
            'user' => $user,
            'can_post' => $this->checkAcl($fConfig, 'write', $user, $project),
            'csrf_token' => $csrfToken,
        ]);
    }
    
    private function verifyCsrf()
    {
        $submittedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = class_exists('\SPP\SPPSession') ? \SPP\SPPSession::getCsrfToken() : ($_SESSION['sppdocs_csrf'] ?? '');
        if (empty($submittedToken) || (empty($sessionToken) && empty($_SESSION['sppdocs_csrf']))) {
            http_response_code(403);
            exit('CSRF Token Invalid');
        }
        if (!hash_equals($sessionToken, $submittedToken) && (!isset($_SESSION['sppdocs_csrf']) || !hash_equals($_SESSION['sppdocs_csrf'], $submittedToken))) {
            http_response_code(403);
            exit('CSRF Token Invalid');
        }
    }

    #[Route('/forums/post', method: 'POST')]
    public function createThread()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $forumSlug = $_POST['forum_slug'] ?? '';
        $title = strip_tags($_POST['title'] ?? '');
        $content = strip_tags($_POST['content'] ?? '');
        
        if (!$projectId || !$forumSlug || !trim($title) || !trim($content)) exit('Invalid input');
        
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        FeatureManager::requireFeature('forums', $project, $projectId);
        
        $forumsDir = $this->getForumsDir($project);
        $forumsConfig = Yaml::parseFile($forumsDir . '/forums.yml');
        $fConfig = $forumsConfig[$forumSlug] ?? null;
        if (!$fConfig) exit('Forum not found');
        
        $user = $this->getUser();
        if (!$this->checkAcl($fConfig, 'write', $user, $project)) exit('Access denied');
        
        $threadId = 't_' . time() . '_' . bin2hex(random_bytes(4));
        $threadData = [
            'title' => $title,
            'author' => $user ? $user['username'] : 'Guest',
            'content' => $content,
            'timestamp' => time(),
            'replies' => []
        ];
        
        $threadFile = $forumsDir . '/' . $forumSlug . '/' . $threadId . '.json';
        file_put_contents($threadFile, json_encode($threadData, JSON_PRETTY_PRINT));
        
        Response::redirect(App::url('forums/thread') . "?projectId={$projectId}&forumSlug={$forumSlug}&threadId={$threadId}");
    }

    #[Route('/forums/reply', method: 'POST')]
    public function postReply()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $forumSlug = $_POST['forum_slug'] ?? '';
        $threadId = $_POST['thread_id'] ?? '';
        $content = strip_tags($_POST['content'] ?? '');
        
        if (!$projectId || !$forumSlug || !$threadId || !trim($content)) exit('Invalid input');
        
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        FeatureManager::requireFeature('forums', $project, $projectId);
        
        $forumsDir = $this->getForumsDir($project);
        $forumsConfig = Yaml::parseFile($forumsDir . '/forums.yml');
        $fConfig = $forumsConfig[$forumSlug] ?? null;
        if (!$fConfig) exit('Forum not found');
        
        $user = $this->getUser();
        if (!$this->checkAcl($fConfig, 'write', $user, $project)) exit('Access denied');
        
        $threadFile = $forumsDir . '/' . $forumSlug . '/' . basename($threadId) . '.json';
        
        // Optimistic Concurrency Control using LOCK_EX
        $fp = fopen($threadFile, 'c+');
        if (flock($fp, LOCK_EX)) {
            $json = stream_get_contents($fp);
            $threadData = json_decode($json, true);
            
            $threadData['replies'][] = [
                'author' => $user ? $user['username'] : 'Guest',
                'content' => $content,
                'timestamp' => time()
            ];
            
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($threadData, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        
        Response::redirect(App::url('forums/thread') . "?projectId={$projectId}&forumSlug={$forumSlug}&threadId={$threadId}");
    }

    #[Route('/forums/solution', method: 'POST')]
    public function markSolution()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $forumSlug = $_POST['forum_slug'] ?? '';
        $threadId = $_POST['thread_id'] ?? '';
        $replyIndex = isset($_POST['reply_index']) ? (int)$_POST['reply_index'] : -1;

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $forumsDir = $this->getForumsDir($project);
        $threadFile = $forumsDir . '/' . $forumSlug . '/' . basename($threadId) . '.json';

        if (file_exists($threadFile)) {
            $threadData = json_decode(file_get_contents($threadFile), true);
            if (($threadData['solved_reply_index'] ?? null) === $replyIndex) {
                unset($threadData['solved_reply_index']);
            } else {
                $threadData['solved_reply_index'] = $replyIndex;
            }
            file_put_contents($threadFile, json_encode($threadData, JSON_PRETTY_PRINT), LOCK_EX);
        }

        Response::redirect(App::url('forums/thread') . "?projectId={$projectId}&forumSlug={$forumSlug}&threadId={$threadId}");
    }

    #[Route('/forums/react', method: 'POST')]
    public function reactReply()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $forumSlug = $_POST['forum_slug'] ?? '';
        $threadId = $_POST['thread_id'] ?? '';
        $replyIndex = isset($_POST['reply_index']) ? (int)$_POST['reply_index'] : -1;
        $emoji = $_POST['emoji'] ?? '👍';

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $forumsDir = $this->getForumsDir($project);
        $threadFile = $forumsDir . '/' . $forumSlug . '/' . basename($threadId) . '.json';

        if (file_exists($threadFile) && $replyIndex >= 0) {
            $threadData = json_decode(file_get_contents($threadFile), true);
            if (isset($threadData['replies'][$replyIndex])) {
                if (!isset($threadData['replies'][$replyIndex]['reactions'])) {
                    $threadData['replies'][$replyIndex]['reactions'] = [];
                }
                $current = $threadData['replies'][$replyIndex]['reactions'][$emoji] ?? 0;
                $threadData['replies'][$replyIndex]['reactions'][$emoji] = $current + 1;
                file_put_contents($threadFile, json_encode($threadData, JSON_PRETTY_PRINT), LOCK_EX);
            }
        }

        Response::redirect(App::url('forums/thread') . "?projectId={$projectId}&forumSlug={$forumSlug}&threadId={$threadId}");
    }
}
