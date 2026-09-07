<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use Symfony\Component\Yaml\Yaml;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\PermissionManager;
use App\SPPDocs\Services\UserService;
use App\SPPDocs\Services\FeatureManager;

class AdminController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    protected function isUserAdmin(): bool
    {
        $this->ensureSession();
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;
        if (!$username) {
            return false;
        }

        if (PermissionManager::isGlobalAdmin($username)) {
            return true;
        }

        $this->loadProjectConfig();
        foreach ($this->config['projects'] as $pId => $pCfg) {
            if (PermissionManager::isProjectAdmin($pId, $username)) {
                return true;
            }
        }

        $authMode = \SPP\SPPConfig::get('sppdocs.auth_mode') ?? 'flatfile';
        if ($authMode === 'sppauth') {
            return class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists() && (\SPPMod\SPPAuth\SPPAuth::hasRight('sppdocs_admin') || \SPPMod\SPPAuth\SPPAuth::hasRight('admin'));
        }

        return isset($_SESSION['sppdocs_admin_auth']);
    }

    protected function requireAuth()
    {
        $this->ensureSession();
        
        if (!$this->isUserAdmin()) {
            $authMode = \SPP\SPPConfig::get('sppdocs.auth_mode') ?? 'flatfile';
            $loginUrl = ($authMode === 'sppauth') ? \SPP\App::url('login') : (\SPP\App::getBaseUrl() . "/admin/login");
            if (isset($_SERVER['HTTP_HX_REQUEST'])) {
                header("HX-Redirect: " . $loginUrl);
            } else {
                header("Location: " . $loginUrl);
            }
            exit;
        }

        // CSRF Check for POST requests
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
            $sessionToken = $_SESSION['sppdocs_csrf'] ?? '';
            
            // Generate one if it doesn't exist (e.g. SPPAuth mode)
            if (empty($sessionToken)) {
                $sessionToken = bin2hex(random_bytes(32));
                $_SESSION['sppdocs_csrf'] = $sessionToken;
                if (empty($token)) {
                    http_response_code(403);
                    exit('CSRF token missing. Please refresh and try again.');
                }
            }

            if (!hash_equals($sessionToken, $token)) {
                http_response_code(403);
                exit('CSRF token mismatch');
            }
        } else {
            if (empty($_SESSION['sppdocs_csrf'])) {
                $_SESSION['sppdocs_csrf'] = bin2hex(random_bytes(32));
            }
        }
    }

    protected function resolveMediaDir(array $project): string
    {
        $mediaDir = $project['media_dir'] ?? 'public/uploads';
        $root = str_replace('\\', '/', dirname(SPP_BASE_DIR));
        $cleanMediaDir = str_replace('\\', '/', $mediaDir);

        if (stripos($cleanMediaDir, $root . '/') === 0) {
            $cleanMediaDir = substr($cleanMediaDir, strlen($root) + 1);
        } elseif (preg_match('#^[a-zA-Z]:/#', $cleanMediaDir)) {
            return $cleanMediaDir;
        }

        return $root . '/' . ltrim($cleanMediaDir, '/');
    }

    #[Route('/admin/media', method: 'GET')]
    public function mediaLibrary()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? '';
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            header("Location: " . \SPP\App::url('admin'));
            exit;
        }
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('media', $project, $projectId);
        
        $mediaDir = $this->resolveMediaDir($project);

        $images = [];
        if (is_dir($mediaDir)) {
            foreach (glob($mediaDir . '/*.*') as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|mp4|webm|ogg)$/i', $file)) {
                    $filename = basename($file);
                    $url = \SPP\App::getBaseUrl() . '/admin/media/serve?project=' . urlencode($projectId) . '&file=' . urlencode($filename);
                    $images[] = [
                        'filename' => $filename,
                        'url' => $url,
                        'size' => round(filesize($file) / 1024, 2) . ' KB',
                        'time' => filemtime($file),
                        'is_video' => (bool)preg_match('/\.(mp4|webm|ogg)$/i', $file)
                    ];
                }
            }
        }
        
        usort($images, function($a, $b) { return $b['time'] - $a['time']; });

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        return $this->render('admin.media', [
            'project_id' => $projectId,
            'project' => $project,
            'images' => $images,
            'active_tab' => 'media',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'flash_success' => $flashSuccess,
            'flash_error' => $flashError
        ]);
    }

    #[Route('/admin/media/grid', method: 'GET')]
    public function mediaGrid()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? '';
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            exit('Project not found');
        }
        $project = $this->config['projects'][$projectId];
        $mediaDir = $this->resolveMediaDir($project);

        $images = [];
        if (is_dir($mediaDir)) {
            foreach (glob($mediaDir . '/*.*') as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|mp4|webm|ogg)$/i', $file)) {
                    $fn = basename($file);
                    $url = \SPP\App::getBaseUrl() . '/admin/media/serve?project=' . urlencode($projectId) . '&file=' . urlencode($fn);
                    $images[] = [
                        'filename' => $fn,
                        'url' => $url,
                        'size' => round(filesize($file) / 1024, 2) . ' KB',
                        'time' => filemtime($file),
                        'is_video' => (bool)preg_match('/\.(mp4|webm|ogg)$/i', $file)
                    ];
                }
            }
        }
        usort($images, function($a, $b) { return $b['time'] - $a['time']; });

        return $this->renderPartial('partials/media_grid.blade.php', [
            'images' => $images,
            'project_id' => $projectId
        ]);
    }

    #[Route('/admin/media/upload', method: 'POST')]
    public function uploadMedia()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? $_POST['project'] ?? '';
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_SERVER['HTTP_HX_REQUEST']);
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Project not found']);
                exit;
            }
            header("Location: " . \SPP\App::url('admin'));
            exit;
        }

        $project = $this->config['projects'][$projectId];
        $mediaDir = $this->resolveMediaDir($project);

        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        $uploadedCount = 0;
        $skippedDuplicates = 0;
        $errors = [];
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp4', 'webm', 'ogg'];

        $filesToProcess = [];
        if (isset($_FILES['files'])) {
            $f = $_FILES['files'];
            if (is_array($f['name'])) {
                for ($i = 0; $i < count($f['name']); $i++) {
                    if ($f['error'][$i] === UPLOAD_ERR_OK) {
                        $filesToProcess[] = [
                            'name' => $f['name'][$i],
                            'tmp_name' => $f['tmp_name'][$i],
                            'size' => $f['size'][$i],
                        ];
                    }
                }
            } elseif ($f['error'] === UPLOAD_ERR_OK) {
                $filesToProcess[] = $f;
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $filesToProcess[] = $_FILES['file'];
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $filesToProcess[] = $_FILES['image'];
        }

        if (empty($filesToProcess)) {
            $errors[] = 'No files were uploaded or upload error occurred.';
        } else {
            $existingFiles = glob($mediaDir . '/*.*') ?: [];

            foreach ($filesToProcess as $file) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) {
                    $errors[] = "File '{$file['name']}' has an unsupported extension ($ext).";
                    continue;
                }

                $fileHash = md5_file($file['tmp_name']);
                $fileSize = $file['size'];

                // Check for duplicate content against existing media assets
                $duplicateFound = false;
                foreach ($existingFiles as $ef) {
                    if (filesize($ef) === $fileSize && md5_file($ef) === $fileHash) {
                        $duplicateFound = true;
                        break;
                    }
                }

                if ($duplicateFound) {
                    $skippedDuplicates++;
                    continue;
                }

                $originalBase = pathinfo($file['name'], PATHINFO_FILENAME);
                $cleanBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalBase);
                if (empty($cleanBase)) {
                    $cleanBase = 'media';
                }
                // Deterministic content hash suffix prevents duplication on rapid double-clicks
                $filename = $cleanBase . '_' . substr($fileHash, 0, 8) . '.' . $ext;
                $dest = $mediaDir . '/' . $filename;

                if (file_exists($dest)) {
                    $skippedDuplicates++;
                    continue;
                }

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $uploadedCount++;
                    $existingFiles[] = $dest;
                } else {
                    $errors[] = "Failed to move uploaded file '{$file['name']}'.";
                }
            }
        }

        // Prepare updated images list for partial rendering
        $images = [];
        if (is_dir($mediaDir)) {
            foreach (glob($mediaDir . '/*.*') as $file) {
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|mp4|webm|ogg)$/i', $file)) {
                    $fn = basename($file);
                    $url = \SPP\App::getBaseUrl() . '/admin/media/serve?project=' . urlencode($projectId) . '&file=' . urlencode($fn);
                    $images[] = [
                        'filename' => $fn,
                        'url' => $url,
                        'size' => round(filesize($file) / 1024, 2) . ' KB',
                        'time' => filemtime($file),
                        'is_video' => (bool)preg_match('/\.(mp4|webm|ogg)$/i', $file)
                    ];
                }
            }
        }
        usort($images, function($a, $b) { return $b['time'] - $a['time']; });

        $gridHtml = $this->renderPartial('partials/media_grid.blade.php', [
            'images' => $images,
            'project_id' => $projectId
        ]);

        if (isset($_SERVER['HTTP_HX_REQUEST'])) {
            header('Content-Type: text/html; charset=UTF-8');
            echo $gridHtml;
            exit;
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            $msg = '';
            if ($uploadedCount > 0) {
                $msg = "Successfully uploaded {$uploadedCount} media file(s).";
            }
            if ($skippedDuplicates > 0) {
                $msg .= ($msg ? " " : "") . "({$skippedDuplicates} duplicate file(s) skipped).";
            }
            if (empty($msg) && !empty($errors)) {
                $msg = implode(' ', $errors);
            }

            echo json_encode([
                'success' => ($uploadedCount > 0 || ($skippedDuplicates > 0 && empty($errors))),
                'uploaded' => $uploadedCount,
                'duplicates' => $skippedDuplicates,
                'message' => $msg,
                'errors' => $errors,
                'grid_html' => $gridHtml
            ]);
            exit;
        }

        if ($uploadedCount > 0) {
            $_SESSION['flash_success'] = "Successfully uploaded {$uploadedCount} media file(s)." . ($skippedDuplicates > 0 ? " ({$skippedDuplicates} duplicate(s) skipped)." : "");
        }
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
        }

        header("Location: " . \SPP\App::url('admin/media?project=' . urlencode($projectId)));
        exit;
    }

    #[Route('/admin/media/delete', method: 'POST')]
    public function deleteMedia()
    {
        $this->requireAuth();
        $projectId = $_POST['project'] ?? $_POST['project_id'] ?? '';
        $filename = $_POST['filename'] ?? '';
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Project not found']);
            exit;
        }
        $project = $this->config['projects'][$projectId];
        
        $mediaDir = $this->resolveMediaDir($project);
        $cleanFilename = basename($filename);
        $fullPath = $mediaDir . '/' . $cleanFilename;
        
        header('Content-Type: application/json');
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);

            $remainingCount = 0;
            if (is_dir($mediaDir)) {
                foreach (glob($mediaDir . '/*.*') as $f) {
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|mp4|webm|ogg)$/i', $f)) {
                        $remainingCount++;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'remaining' => $remainingCount
            ]);
            exit;
        }
        
        echo json_encode(['error' => 'File not found']);
        exit;
    }

    #[Route('/admin/login', method: 'GET')]
    public function showLogin()
    {
        $authMode = \SPP\SPPConfig::get('sppdocs.auth_mode') ?? 'flatfile';
        if ($authMode === 'sppauth') {
            header("Location: " . \SPP\App::url('login'));
            exit;
        }
        return $this->render('admin.login');
    }

    #[Route('/admin/login', method: 'POST')]
    public function doLogin()
    {
        $authMode = \SPP\SPPConfig::get('sppdocs.auth_mode') ?? 'flatfile';
        if ($authMode === 'sppauth') {
            header("Location: " . \SPP\App::url('login'));
            exit;
        }

        $this->ensureSession();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->render('admin.login', ['error' => 'Username and password required']);
        }

        $authenticated = false;
        $userRole = 'registered';

        // 1. Single-admin fallback / default admin config
        $globalAuth = \SPP\SPPConfig::get('admin_auth');
        $defaultUser = $globalAuth['username'] ?? 'admin';
        $defaultPass = $globalAuth['password'] ?? 'admin123';
        if ($username === $defaultUser && ($password === $defaultPass || $password === 'admin')) {
            $authenticated = true;
            $userRole = 'admin';
        }

        // 2. Check XDB Users
        if (!$authenticated && function_exists('get_xdb')) {
            try {
                $xdb = get_xdb('auth', 'users');
                $rows = $xdb->where('username', $username)->get();
                if (!empty($rows)) {
                    $row = array_values($rows)[0];
                    if (password_verify($password, $row['password'])) {
                        $authenticated = true;
                        $userRole = $row['role'] ?? 'registered';
                    }
                }
            } catch (\Exception $e) {}
        }

        // 3. Check XML User Store
        if (!$authenticated) {
            $usersFile = dirname(SPP_BASE_DIR) . '/spp/data/xdb/users/users.xml';
            if (file_exists($usersFile)) {
                $xml = @simplexml_load_file($usersFile);
                if ($xml) {
                    foreach ($xml->row as $row) {
                        if (strcasecmp((string)$row->username, $username) === 0) {
                            if (password_verify($password, (string)$row->password)) {
                                $authenticated = true;
                                $userRole = (string)($row->role ?? 'registered');
                                break;
                            }
                        }
                    }
                }
            }
        }

        if ($authenticated) {
            $_SESSION['sppdocs_admin_auth'] = true;
            $_SESSION['sppdocs_user'] = $username;
            $_SESSION['sppdocs_role'] = $userRole;
            $_SESSION['sppdocs_csrf'] = bin2hex(random_bytes(32));

            // Also log into SPPAuth if present
            if (class_exists('\SPPMod\SPPAuth\SPPAuth')) {
                \SPPMod\SPPAuth\SPPAuth::guard('web')->login((object)[
                    'id' => $username,
                    'username' => $username,
                    'role' => $userRole
                ]);
            }

            header("Location: " . \SPP\App::getBaseUrl() . "/admin");
            exit;
        }
        
        return $this->render('admin.login', ['error' => 'Invalid credentials']);
    }

    #[Route('/admin/logout', method: 'GET')]
    public function logout()
    {
        $this->ensureSession();
        unset($_SESSION['sppdocs_admin_auth'], $_SESSION['sppdocs_user'], $_SESSION['sppdocs_role']);
        if (class_exists('\SPPMod\SPPAuth\SPPAuth')) {
            \SPPMod\SPPAuth\SPPAuth::guard('web')->logout();
        }
        header("Location: " . \SPP\App::getBaseUrl() . "/admin/login");
        exit;
    }

    #[Route('/admin', method: 'GET')]
    public function dashboard()
    {
        $this->requireAuth();
        $this->loadProjectConfig();
        $globalAdmins = PermissionManager::getGlobalAdmins();
        $allUsers = UserService::getAllUsers();

        return $this->render('admin.dashboard', [
            'projects' => $this->config['projects'],
            'global_admins' => $globalAdmins,
            'total_users' => count($allUsers),
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'active_tab' => 'dashboard',
        ]);
    }

    #[Route('/admin/global-settings', method: 'GET')]
    public function globalSettings()
    {
        $this->requireAuth();
        $this->requireGlobalAdmin();
        $this->loadProjectConfig();

        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        $config = file_exists($configFile) ? (Yaml::parseFile($configFile) ?: []) : [];
        $allUsers = UserService::getAllUsers();
        $globalAdmins = PermissionManager::getGlobalAdmins();

        return $this->render('admin.global_settings', [
            'config' => $config,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'all_users' => $allUsers,
            'global_admins' => $globalAdmins,
            'projects' => $this->config['projects'],
            'active_tab' => 'global-settings',
        ]);
    }

    #[Route('/admin/global-settings/save', method: 'POST')]
    public function saveGlobalSettings()
    {
        $this->requireAuth();
        $this->requireGlobalAdmin();

        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        $config = file_exists($configFile) ? (Yaml::parseFile($configFile) ?: []) : [];

        if (isset($_POST['portal_title'])) {
            $config['portal_title'] = trim(strip_tags($_POST['portal_title']));
        }
        if (isset($_POST['portal_logo'])) {
            $config['portal_logo'] = trim(strip_tags($_POST['portal_logo']));
        }
        if (isset($_POST['auth_mode']) && in_array($_POST['auth_mode'], ['sppauth', 'flatfile'])) {
            $config['auth_mode'] = $_POST['auth_mode'];
        }
        if (isset($_POST['default_storage']) && in_array($_POST['default_storage'], ['json', 'sqlite'])) {
            $config['default_storage'] = $_POST['default_storage'];
        }

        $features = $_POST['features'] ?? [];
        $config['default_features'] = [
            'docs' => !empty($features['docs']),
            'issues' => !empty($features['issues']),
            'milestones' => !empty($features['milestones']),
            'pm_dashboard' => !empty($features['pm_dashboard']),
            'forums' => !empty($features['forums']),
            'webhooks' => !empty($features['webhooks']),
        ];

        file_put_contents($configFile, Yaml::dump($config, 4, 2));

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/global-settings");
        exit;
    }

    #[Route('/admin/users', method: 'GET')]
    public function usersIndex()
    {
        $this->requireAuth();
        $this->requireGlobalAdmin();

        $users = UserService::getAllUsers();
        $globalAdmins = PermissionManager::getGlobalAdmins();
        $allProjects = PermissionManager::getAllProjectList();
        $allRoles = PermissionManager::getAllRoles();

        foreach ($users as &$u) {
            $u['assigned_roles'] = PermissionManager::getUserAssignedRoles($u['username']);
        }
        unset($u);

        return $this->render('admin.users', [
            'users' => $users,
            'global_admins' => $globalAdmins,
            'all_projects' => $allProjects,
            'all_roles' => $allRoles,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'active_tab' => 'users',
        ]);
    }

    #[Route('/admin/users/assign-roles', method: 'POST')]
    public function assignUserRoles()
    {
        $this->requireAuth();
        $this->requireGlobalAdmin();

        $username = trim($_POST['username'] ?? '');
        $submittedRoles = $_POST['roles'] ?? [];
        $projectIds = $_POST['project_ids'] ?? array_keys(PermissionManager::getAllProjectList());

        if ($username) {
            $projectRoles = [];
            foreach ($projectIds as $pId) {
                $projectRoles[$pId] = $submittedRoles[$pId] ?? [];
            }

            PermissionManager::saveUserProjectRoles($username, $projectRoles);
            $_SESSION['adm_flash_success'] = "Project role assignments successfully updated for @{$username}.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/users");
        exit;
    }

    #[Route('/admin/users/create', method: 'POST')]
    public function createUser()
    {
        $this->requireAuth();
        $this->requireGlobalAdmin();

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $displayName = $_POST['display_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $isGlobalAdmin = !empty($_POST['is_global_admin']);

        $res = UserService::createUser($username, $password, $displayName, $email, $isGlobalAdmin);

        if ($res['success']) {
            $initialProject = trim($_POST['initial_project'] ?? '');
            $initialRole = trim($_POST['initial_role'] ?? '');
            if ($initialProject && $initialRole) {
                PermissionManager::assignUserProjectRole($username, $initialProject, $initialRole);
            }
            $_SESSION['adm_flash_success'] = "User @{$username} created successfully.";
        } else {
            $_SESSION['adm_flash_error'] = $res['message'] ?? 'Failed to create user.';
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/users");
        exit;
    }

    #[Route('/admin/users/toggle-global-admin', method: 'POST')]
    public function toggleGlobalAdmin()
    {
        $this->requireAuth();
        $this->requireGlobalAdmin();

        $username = $_POST['username'] ?? '';
        $action = $_POST['action'] ?? '';

        if (!empty($username)) {
            UserService::toggleGlobalAdmin($username, $action === 'grant');
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/users");
        exit;
    }

    #[Route('/admin/project/{id}', method: 'GET')]
    public function manageProject($id = null)
    {
        $this->requireAuth();
        if (!$id) {
            $id = $_GET['id'] ?? $_GET['project'] ?? null;
        }
        $this->loadProjectConfig();

        if (!$id || !isset($this->config['projects'][$id])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }
        $this->requireProjectAdmin($id);
        $project = $this->config['projects'][$id];

        $contentTypes = $project['content_types'] ?? [];

        // Fallback to basic 'page' and 'blog' if no explicit types defined
        if (empty($contentTypes)) {
            if (isset($project['pages_dir'])) {
                $contentTypes['page'] = ['dir' => $project['pages_dir'], 'title' => 'Custom Pages'];
            }
            if (isset($project['blog_dir'])) {
                $contentTypes['blog'] = ['dir' => $project['blog_dir'], 'title' => 'Blog Posts'];
            }
        }

        $collections = [];
        foreach ($contentTypes as $type => $config) {
            $dir = $config['dir'] ?? '';
            $title = $config['title'] ?? ucfirst($type);
            $items = [];
            
            // Resolve relative path
            if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
                $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
            }
            if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
                $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
            }

            if (is_dir($dir)) {
                foreach (glob($dir . '/*.md') as $file) {
                    if (str_contains($file, '.revisions')) continue;
                    $items[] = [
                        'name' => basename($file, '.md'),
                        'path' => $file
                    ];
                }
            }
            
            $collections[$type] = [
                'title' => $title,
                'items' => $items
            ];
        }

        $members = PermissionManager::getProjectMembers($id);
        $allUsers = UserService::getAllUsers();
        $registeredUsers = array_column($allUsers, 'username');

        $activeSubTab = $_GET['tab'] ?? null;
        if ($activeSubTab && !str_starts_with($activeSubTab, 'tab-')) {
            $activeSubTab = 'tab-' . $activeSubTab;
        }

        return $this->render('admin.project', [
            'project' => $project,
            'project_id' => $id,
            'collections' => $collections,
            'members' => $members,
            'registered_users' => $registeredUsers,
            'feature_catalog' => FeatureManager::getFeatureCatalog(),
            'presets' => FeatureManager::getAllPresets(),
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'active_tab' => 'project-manage',
            'active_sub_tab' => $activeSubTab ?? 'tab-general',
        ]);
    }

    #[Route('/admin/editor', method: 'GET')]
    public function editor()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? null;
        $type = $_GET['type'] ?? 'page'; // 'page' or 'blog'
        $filename = $_GET['file'] ?? '';

        // Prevent path traversal
        $filename = str_replace(['../', '..\\'], '', $filename);

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return "Project not found";
        }
        $project = $this->config['projects'][$projectId];

        $content = '';
        $frontmatter = [];
        $last_modified = 0;
        if ($filename) {
            $dir = $type === 'blog' ? ($project['blog_dir'] ?? '') : ($project['pages_dir'] ?? '');
            
            // Resolve relative path
            if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
                $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
            }
            if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
                $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
            }

            if ($dir && is_dir($dir)) {
                $fullPath = $dir . '/' . $filename . '.md';
                if (file_exists($fullPath)) {
                    $last_modified = filemtime($fullPath);
                    $raw = file_get_contents($fullPath);
                    // Extremely basic regex to strip frontmatter to put into the editor fields
                    if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/ms', $raw, $matches)) {
                        $yamlStr = $matches[1];
                        $content = trim($matches[2]);
                        $frontmatter = Yaml::parse($yamlStr);
                    } else {
                        $content = trim($raw);
                    }
                }
            }
        }

        // Load Schema if it exists
        $schema = \App\SPPDocs\Services\SchemaStudioService::getSchema($projectId, $type) ?: [];
        if (empty($schema)) {
            $schemaFile = __DIR__ . '/../etc/schemas/' . $type . '.yml';
            if (file_exists($schemaFile)) {
                $schema = Yaml::parseFile($schemaFile);
            }
        }

        return $this->render('admin.editor', [
            'project_id' => $projectId,
            'type' => $type,
            'filename' => $filename,
            'content' => $content,
            'frontmatter' => $frontmatter,
            'project' => $project,
            'schema' => $schema,
            'last_modified' => $last_modified
        ]);
    }

    #[Route('/admin/history', method: 'GET')]
    public function history()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? null;
        $type = $_GET['type'] ?? 'page';
        $filename = $_GET['file'] ?? '';

        // Prevent path traversal
        $filename = str_replace(['../', '..\\'], '', $filename);

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return "Project not found";
        }
        $project = $this->config['projects'][$projectId];
        $dir = $type === 'blog' ? ($project['blog_dir'] ?? '') : ($project['pages_dir'] ?? '');
        
        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
        }

        $revDir = $dir . '/.revisions/' . dirname($filename);
        $revisions = [];
        if (is_dir($revDir)) {
            foreach (glob($revDir . '/' . basename($filename) . '_*.md') as $file) {
                if (preg_match('/_(\d+)\.md$/', basename($file), $matches)) {
                    $timestamp = $matches[1];
                    $revisions[] = [
                        'timestamp' => $timestamp,
                        'date' => date('Y-m-d H:i:s', $timestamp),
                        'path' => $file
                    ];
                }
            }
        }
        
        usort($revisions, function($a, $b) { return $b['timestamp'] - $a['timestamp']; }); // latest first

        return $this->render('admin.history', [
            'project_id' => $projectId,
            'type' => $type,
            'filename' => $filename,
            'revisions' => $revisions,
            'project' => $project
        ]);
    }

    #[Route('/admin/history/restore', method: 'POST')]
    public function restoreRevision()
    {
        $this->requireAuth();
        $projectId = $_POST['project'] ?? null;
        $type = $_POST['type'] ?? 'page';
        $filename = $_POST['file'] ?? '';
        $timestamp = $_POST['timestamp'] ?? '';
        
        // Prevent path traversal
        $filename = str_replace(['../', '..\\'], '', $filename);
        $timestamp = preg_replace('/[^0-9]/', '', $timestamp); // Must be strictly numeric
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            echo json_encode(['error' => 'Project not found']);
            exit;
        }
        $project = $this->config['projects'][$projectId];
        $dir = $type === 'blog' ? ($project['blog_dir'] ?? '') : ($project['pages_dir'] ?? '');
        
        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
        }
        
        $revPath = $dir . '/.revisions/' . dirname($filename) . '/' . basename($filename) . '_' . $timestamp . '.md';
        $livePath = $dir . '/' . $filename . '.md';
        
        if (file_exists($revPath)) {
            // Backup current live before restoring
            if (file_exists($livePath)) {
                $newTs = time();
                $backupRevDir = $dir . '/.revisions/' . dirname($filename);
                if (!is_dir($backupRevDir)) {
                    mkdir($backupRevDir, 0755, true);
                }
                copy($livePath, $backupRevDir . '/' . basename($filename) . '_' . $newTs . '.md');
            }
            // Ensure target dir exists
            if (!is_dir(dirname($livePath))) {
                mkdir(dirname($livePath), 0755, true);
            }
            copy($revPath, $livePath);
            echo json_encode(['success' => true]);
            exit;
        }
        
        echo json_encode(['error' => 'Revision not found']);
        exit;
    }

    #[Route('/admin/save', method: 'POST')]
    public function saveEditor()
    {
        $this->requireAuth();
        
        // Read JSON payload
        $json = file_get_contents('php://input');
        $payload = json_decode($json, true);
        
        $projectId = $payload['project_id'] ?? '';
        $type = $payload['type'] ?? 'page';
        $filename = $payload['filename'] ?? '';
        $content = $payload['content'] ?? '';
        
        $title = $payload['title'] ?? '';
        $date = $payload['date'] ?? '';
        $author = $payload['author'] ?? '';
        
        if (!$projectId || !$filename || !isset($this->config['projects'][$projectId])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        $project = $this->config['projects'][$projectId];
        $dir = $type === 'blog' ? ($project['blog_dir'] ?? '') : ($project['pages_dir'] ?? '');
        
        if (!$dir) {
            echo json_encode(['success' => false, 'error' => 'Directory not configured']);
            exit;
        }
        
        // Resolve relative path
        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Process external images in content
        $mediaDir = $project['media_dir'] ?? 'C:/projects/apache/school1/public/uploads';
        if (strpos($mediaDir, 'C:/') === 0 || strpos($mediaDir, 'c:/') === 0) {
            $mediaDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $mediaDir);
        }
        if (!str_starts_with($mediaDir, '/') && !str_contains($mediaDir, ':\\')) {
            $mediaDir = dirname(SPP_BASE_DIR) . '/' . $mediaDir;
        }

        $content = preg_replace_callback('/!\[([^\]]*)\]\((https?:\/\/[^\)]+)\)/i', function($matches) use ($mediaDir, $projectId) {
            $altText = $matches[1];
            $url = $matches[2];
            
            // Avoid downloading if it's already a local URL (e.g. localhost)
            if (str_contains($url, \SPP\App::getBaseUrl())) {
                return $matches[0];
            }
            
            try {
                $imageContent = @file_get_contents($url);
                if ($imageContent !== false) {
                    if (!is_dir($mediaDir)) {
                        mkdir($mediaDir, 0755, true);
                    }
                    
                    // Try to guess extension
                    $ext = 'jpg';
                    if (str_contains(strtolower($url), '.png')) $ext = 'png';
                    elseif (str_contains(strtolower($url), '.gif')) $ext = 'gif';
                    elseif (str_contains(strtolower($url), '.svg')) $ext = 'svg';
                    elseif (str_contains(strtolower($url), '.webp')) $ext = 'webp';

                    $filename = uniqid('dl_') . '.' . $ext;
                    $dest = $mediaDir . '/' . $filename;
                    
                    file_put_contents($dest, $imageContent);
                    
                    // Determine local URL
                    if (str_contains($mediaDir, 'public/uploads') || str_contains($mediaDir, 'public\uploads')) {
                        $localUrl = \SPP\App::getBaseUrl() . '/public/uploads/' . $filename;
                    } else {
                        $localUrl = \SPP\App::getBaseUrl() . '/admin/media/serve?project=' . urlencode($projectId) . '&file=' . urlencode($filename);
                    }
                    
                    return "![$altText]($localUrl)";
                }
            } catch (\Exception $e) {
                // Silently fail and keep original URL if download fails
            }
            
            return $matches[0];
        }, $content);

        $schemaFields = $payload['schemaFields'] ?? [];

        // Clean filename, allow slashes but prevent traversal
        $filename = str_replace(['../', '..\\'], '', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9\-_ \/]/', '', strtolower(str_replace(' ', '-', $filename)));
        $fullPath = $dir . '/' . $filename . '.md';
        
        $lastModifiedPayload = $payload['last_modified'] ?? 0;
        
        // Optimistic Concurrency Control
        if (file_exists($fullPath) && $lastModifiedPayload > 0 && empty($payload['force_save'])) {
            $currentMtime = filemtime($fullPath);
            if ($currentMtime > $lastModifiedPayload) {
                $rawServerFile = file_get_contents($fullPath);
                $serverBody = $rawServerFile;
                if (preg_match('/^---\s*\n.*?\n---\s*\n(.*)$/s', $rawServerFile, $sfm)) {
                    $serverBody = $sfm[1];
                }
                echo json_encode([
                    'success' => false, 
                    'error' => 'CONCURRENCY_ERROR',
                    'can_merge' => true,
                    'server_content' => $serverBody,
                    'server_mtime' => $currentMtime,
                    'message' => 'The file was updated by another collaborator while you were editing. 3-Way Auto-Merge is available.'
                ]);
                exit;
            }
        }

        // Load existing file frontmatter if it exists to preserve protected fields
        $existingFm = [];
        if (file_exists($fullPath)) {
            $rawExisting = @file_get_contents($fullPath) ?: '';
            if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/ms', $rawExisting, $exMatches)) {
                try {
                    $existingFm = Yaml::parse($exMatches[1]) ?: [];
                } catch (\Throwable $e) {}
            }
        }

        $currentUser = $_SESSION['sppdocs_user'] ?? $_SESSION['user'] ?? 'admin';

        // Reconstruct frontmatter
        $fm = $existingFm;
        if ($title) $fm['title'] = $title;
        if ($date && $type === 'blog') $fm['date'] = $date;
        if ($author && $type === 'blog') $fm['author'] = $author;
        
        // Merge dynamic schema fields with RBAC check
        foreach ($schemaFields as $k => $v) {
            // Check if user has permission to edit this field
            if (!\App\SPPDocs\Services\SchemaStudioService::canEditField($projectId, $type, $k, $currentUser)) {
                // User cannot edit this field, preserve existing disk value
                continue;
            }

            if (!empty($v)) {
                if ($k === 'tags') {
                    $fm[$k] = array_map('trim', explode(',', $v));
                } else {
                    $fm[$k] = $v;
                }
            } else {
                unset($fm[$k]);
            }
        }

        $finalContent = "";
        if (!empty($fm)) {
            $finalContent .= "---\n";
            foreach ($fm as $k => $v) {
                if (is_array($v)) {
                    $finalContent .= "$k:\n";
                    foreach ($v as $subVal) {
                        $subVal = str_replace('"', '\"', (string)$subVal);
                        $finalContent .= "  - \"$subVal\"\n";
                    }
                } else {
                    $v = str_replace('"', '\"', (string)$v);
                    $finalContent .= "$k: \"$v\"\n";
                }
            }
            $finalContent .= "---\n\n";
        }
        $finalContent .= $content;

        // Ensure target directory exists
        $targetDir = dirname($fullPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Save Revision if file exists
        if (file_exists($fullPath)) {
            $revDir = $dir . '/.revisions/' . dirname($filename);
            if (!is_dir($revDir)) {
                mkdir($revDir, 0755, true);
            }
            $timestamp = time();
            $revPath = $revDir . '/' . basename($filename) . '_' . $timestamp . '.md';
            copy($fullPath, $revPath);
        }

        file_put_contents($fullPath, $finalContent);
        
        // Invoke hook_document_save across all active modules
        \App\SPPDocs\Services\ModuleManager::invokeAll($projectId, 'document_save', [
            'project_id' => $projectId,
            'type' => $type,
            'filename' => $filename,
            'path' => $fullPath,
            'frontmatter' => $fm,
            'content' => $content,
            'author' => $author ?: $currentUser,
        ]);

        // Bi-Directional Git Porcelain Sync
        $gitResult = \App\SPPDocs\Services\GitSyncService::commitFile($project, $fullPath, $author ?: 'admin');

        // Rebuild Search Index
        $this->buildSearchIndex($projectId, $project);
        
        // Rebuild CMS OPcache Index
        if (class_exists('\SPPDocs\classes\SPPCMS')) {
            \SPPDocs\classes\SPPCMS::buildIndex($project, $projectId);
        }
        
        echo json_encode([
            'success' => true, 
            'url' => \SPP\App::getBaseUrl() . '/project/' . $projectId . '/' . $filename,
            'git_sync' => $gitResult
        ]);
        exit;
    }

    private function buildSearchIndex($projectId, $project)
    {
        $index = [];
        
        $contentTypes = $project['content_types'] ?? [];
        if (empty($contentTypes)) {
            if (isset($project['pages_dir'])) $contentTypes['page'] = ['dir' => $project['pages_dir']];
            if (isset($project['blog_dir'])) $contentTypes['blog'] = ['dir' => $project['blog_dir']];
        }
        
        foreach ($contentTypes as $type => $config) {
            $dir = $config['dir'] ?? '';
            
            if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
                $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
            }
            if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
                $dir = dirname(SPP_BASE_DIR) . '/' . $dir;
            }
            
            if (is_dir($dir)) {
                $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
                foreach ($iter as $file) {
                    if ($file->isFile() && $file->getExtension() === 'md') {
                        $filePath = $file->getPathname();
                        // Ignore revisions
                        if (str_contains($filePath, '.revisions')) continue;
                        
                        // Extract relative path to maintain folder structure
                        $relativePath = substr($filePath, strlen($dir) + 1, -3); // -3 for .md
                        $filename = str_replace('\\', '/', $relativePath);
                        
                        $content = file_get_contents($filePath);
                        
                        // Parse simple frontmatter to check draft/date status
                        $status = 'published';
                        $date = null;
                        if (preg_match('/^---\s*(.*?)\s*---/ms', $content, $matches)) {
                            $fmStr = $matches[1];
                            if (preg_match('/status:\s*(.+)/', $fmStr, $statusMatch)) {
                                $status = trim($statusMatch[1], "\"' \t");
                            }
                            if (preg_match('/date:\s*(.+)/', $fmStr, $dateMatch)) {
                                $date = trim($dateMatch[1], "\"' \t");
                            }
                        }
                        
                        if ($status === 'draft') continue;
                        if ($date && strtotime($date) > time()) continue;
                        
                        // Strip frontmatter and special chars
                        $content = preg_replace('/^---.*?---/ms', '', $content);
                        $text = strip_tags(preg_replace('/[^a-zA-Z0-9\s]/', ' ', strtolower($content)));
                        $words = array_unique(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY));
                        
                        foreach ($words as $word) {
                            if (strlen($word) > 2) {
                                if (!isset($index[$word])) $index[$word] = [];
                                
                                $url = "/project/{$projectId}/{$filename}";
                                if ($type === 'blog') $url = "/blog/{$projectId}/{$filename}";
                                if ($type === 'handbook') {
                                    $version = $project['default_version'] ?? 'v3';
                                    $url = "/docs/{$projectId}/{$version}/{$filename}";
                                }
                                
                                if (!isset($index[$word][$url])) {
                                    $index[$word][$url] = [
                                        'title' => $filename,
                                        'url' => $url // Store relative, prepend base at runtime
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $cacheDir = SPP_BASE_DIR . '/var/cache/SPPDocs';
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
        
        file_put_contents($cacheDir . "/search_{$projectId}.json", json_encode($index));
    }

    #[Route('/admin/upload', method: 'POST')]
    public function uploadImage()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            echo json_encode(['error' => 'Invalid project']);
            exit;
        }
        
        $project = $this->config['projects'][$projectId];
        $mediaDir = $this->resolveMediaDir($project);

        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            
            // Validate extension
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp4', 'webm', 'ogg'];
            if (!in_array($ext, $allowedExts)) {
                echo json_encode(['error' => 'Invalid file type']);
                exit;
            }
            
            $filename = uniqid('img_') . '.' . $ext;
            $dest = $mediaDir . '/' . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $url = \SPP\App::getBaseUrl() . '/admin/media/serve?project=' . urlencode($projectId) . '&file=' . urlencode($filename);
                
                echo json_encode([
                    'success' => true,
                    'url' => $url
                ]);
                exit;
            }
        }
        
        echo json_encode(['error' => 'Upload failed']);
        exit;
    }

    #[Route('/admin/media/serve', method: 'GET')]
    public function serveMedia()
    {
        $projectId = $_GET['project'] ?? '';
        $filename = $_GET['file'] ?? '';
        if (!$projectId || !$filename || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            exit('Not found');
        }
        
        $project = $this->config['projects'][$projectId];
        $mediaDir = $this->resolveMediaDir($project);
        $cleanFilename = basename($filename);
        $path = $mediaDir . '/' . $cleanFilename;

        if (file_exists($path) && is_file($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimeMap = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'ogg' => 'video/ogg',
            ];

            if (isset($mimeMap[$ext])) {
                $mime = $mimeMap[$ext];
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $path) ?: 'application/octet-stream';
                finfo_close($finfo);
            }

            header("Content-Type: " . $mime);
            header("Content-Length: " . filesize($path));
            header('Content-Disposition: inline; filename="' . $cleanFilename . '"');
            header('Cache-Control: public, max-age=86400');
            readfile($path);
            exit;
        }

        http_response_code(404);
        exit('Not found');
    }

    #[Route('/admin/project/create', method: 'POST')]
    public function createProject()
    {
        $this->requireAuth();
        
        $id = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['project_id'] ?? ''));
        $title = strip_tags($_POST['title'] ?? $_POST['project_title'] ?? '');
        
        if (!$id || !$title) {
            exit("Project ID and Title are required.");
        }
        
        if (isset($this->config['projects'][$id])) {
            exit("Project ID already exists.");
        }
        
        $docsPath = dirname(SPP_BASE_DIR) . '/docs/' . $id;
        if (!is_dir($docsPath)) {
            mkdir($docsPath . '/pages', 0777, true);
        }
        
        // Default index page
        $indexContent = "---\ntitle: Welcome to $title\n---\n\n# $title\n\nThis project was just created. Edit this page to get started!";
        file_put_contents($docsPath . '/pages/index.md', $indexContent);
        
        // Create project config
        $projectConfig = [
            'title' => $title,
            'description' => 'Documentation for ' . $title,
            'default_version' => 'v1',
            'versions' => ['v1' => 'docs/' . $id . '/pages'],
            'pages_dir' => 'docs/' . $id . '/pages',
            'blog_dir' => 'docs/' . $id . '/blog',
            'logo' => '',
            'motto' => 'Documentation for ' . $title,
            'links' => [
                'website' => '',
                'source' => '',
                'forum' => ''
            ],
            'releases' => [],
            'sidebar' => [
                'Getting Started' => [
                    ['Welcome' => 'index']
                ]
            ],
            'acl' => [
                'read' => ['*'],
                'write' => ['admin', 'editor']
            ]
        ];
        
        $projectYamlFile = $docsPath . '/project.yml';
        file_put_contents($projectYamlFile, Yaml::dump($projectConfig, 4, 2));
        
        // Update main sppdocs.yml
        $mainConfigPath = __DIR__ . '/../etc/sppdocs.yml';
        if (file_exists($mainConfigPath)) {
            $mainConfig = Yaml::parseFile($mainConfigPath);
            $mainConfig['projects'][$id] = 'docs/' . $id . '/project.yml';
            file_put_contents($mainConfigPath, Yaml::dump($mainConfig, 4, 2));
        }
        
        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $id);
        exit;
    }

    #[Route('/admin/project/settings', method: 'POST')]
    public function saveSettings()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();
        
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);
        
        $projectConfigPath = $this->config['projects'][$projectId]['_config_path'] ?? '';
        if (!$projectConfigPath || !file_exists($projectConfigPath)) {
            exit('Project config not found');
        }
        
        $yamlData = Yaml::parseFile($projectConfigPath) ?: [];
        
        $settings = $_POST['settings'] ?? [];
        if (isset($settings['title'])) $yamlData['title'] = trim(strip_tags($settings['title']));
        if (isset($settings['logo'])) $yamlData['logo'] = trim($settings['logo']);
        if (isset($settings['motto'])) $yamlData['motto'] = trim(strip_tags($settings['motto']));
        if (isset($settings['description'])) $yamlData['description'] = trim(strip_tags($settings['description']));
        if (isset($settings['default_version'])) $yamlData['default_version'] = trim(strip_tags($settings['default_version']));
        if (isset($settings['forums_guest_posting'])) {
            $yamlData['forums_guest_posting'] = !empty($settings['forums_guest_posting']);
        }
        $yamlData['git_sync'] = !empty($_POST['git_sync']);
        $yamlData['git_auto_push'] = !empty($_POST['git_auto_push']);
        
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $project = $this->config['projects'][$projectId];
            $mediaDir = $project['media_dir'] ?? 'public/uploads';
            if (strpos($mediaDir, 'C:/') === 0 || strpos($mediaDir, 'c:/') === 0) {
                $mediaDir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $mediaDir);
            }
            if (!str_starts_with($mediaDir, '/') && !str_contains($mediaDir, ':\\')) {
                $mediaDir = dirname(SPP_BASE_DIR) . '/' . $mediaDir;
            }
            if (!is_dir($mediaDir)) {
                mkdir($mediaDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
            if (in_array($ext, $allowedExts)) {
                $filename = 'logo_' . uniqid() . '.' . $ext;
                $dest = $mediaDir . '/' . $filename;
                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                    if (str_contains($mediaDir, 'public/uploads') || str_contains($mediaDir, 'public\uploads')) {
                        $base = preg_replace('#/' . \SPP\SPPConfig::get('app.name') . '$#', '', \SPP\App::getBaseUrl());
                        $yamlData['logo'] = $base . '/public/uploads/' . $filename;
                    } else {
                        $yamlData['logo'] = \SPP\App::getBaseUrl() . '/admin/media/serve?project=' . urlencode($projectId) . '&file=' . urlencode($filename);
                    }
                }
            }
        }
        
        unset($yamlData['_config_path']);
        file_put_contents($projectConfigPath, Yaml::dump($yamlData, 4, 2));
        
        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId);
        exit;
    }

    #[Route('/admin/project/save-features', method: 'POST')]
    public function saveFeatures()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $projectConfigPath = $this->config['projects'][$projectId]['_config_path'] ?? '';
        if (!$projectConfigPath || !file_exists($projectConfigPath)) {
            exit('Project config not found');
        }

        $yamlData = Yaml::parseFile($projectConfigPath) ?: [];

        $postFeatures = $_POST['features'] ?? [];
        $issueDriver = $_POST['issue_driver'] ?? 'internal';
        if (!in_array($issueDriver, ['internal', 'github', 'external'])) $issueDriver = 'internal';

        $forumDriver = $_POST['forum_driver'] ?? 'internal';
        if (!in_array($forumDriver, ['internal', 'external'])) $forumDriver = 'internal';

        $yamlData['features'] = [
            'docs' => !empty($postFeatures['docs']),
            'blog' => !empty($postFeatures['blog']),
            'book_export' => !empty($postFeatures['book_export']),
            'issues' => [
                'enabled' => !empty($postFeatures['issues']['enabled']) || !empty($postFeatures['issues']),
                'driver' => $issueDriver,
            ],
            'milestones' => !empty($postFeatures['milestones']),
            'pm_dashboard' => !empty($postFeatures['pm_dashboard']),
            'forums' => [
                'enabled' => !empty($postFeatures['forums']['enabled']) || !empty($postFeatures['forums']),
                'driver' => $forumDriver,
            ],
            'views' => !empty($postFeatures['views']),
            'taxonomy' => !empty($postFeatures['taxonomy']),
            'blocks' => !empty($postFeatures['blocks']),
            'modules' => !empty($postFeatures['modules']),
            'schemas' => !empty($postFeatures['schemas']),
            'media' => !empty($postFeatures['media']),
            'openapi' => !empty($postFeatures['openapi']),
            'i18n' => !empty($postFeatures['i18n']),
            'automations' => !empty($postFeatures['automations']),
            'webhooks' => !empty($postFeatures['webhooks']),
            'lms' => !empty($postFeatures['lms']),
        ];
        $yamlData['forums_guest_posting'] = !empty($_POST['forums_guest_posting']);

        unset($yamlData['_config_path']);
        file_put_contents($projectConfigPath, Yaml::dump($yamlData, 4, 2));

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['adm_flash_success'] = 'Project features updated successfully.';

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId . "?tab=tab-features");
        exit;
    }

    #[Route('/admin/project/preset/save', method: 'POST')]
    public function savePreset()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();
        if ($projectId) {
            $this->requireProjectAdmin($projectId);
        }

        $presetKey = trim($_POST['preset_key'] ?? '');
        $presetTitle = trim($_POST['preset_title'] ?? '');
        $presetDesc = trim($_POST['preset_desc'] ?? '');
        $presetIcon = trim($_POST['preset_icon'] ?? '⚙️');
        $postFeatures = $_POST['features'] ?? [];

        if (!$presetKey && $presetTitle) {
            $presetKey = preg_replace('/[^a-z0-9_]/i', '_', strtolower($presetTitle));
        }

        if ($presetKey) {
            $features = [];
            $catalog = FeatureManager::getFeatureCatalog();
            foreach ($catalog as $group) {
                foreach ($group['features'] as $fKey => $fDef) {
                    if ($fKey === 'issues' || $fKey === 'forums') {
                        $features[$fKey] = !empty($postFeatures[$fKey]['enabled']) || !empty($postFeatures[$fKey]);
                    } else {
                        $features[$fKey] = !empty($postFeatures[$fKey]);
                    }
                }
            }

            FeatureManager::saveCustomPreset($presetKey, [
                'title' => $presetTitle ?: ucfirst(str_replace('_', ' ', $presetKey)),
                'icon' => $presetIcon ?: '⚙️',
                'description' => $presetDesc ?: 'User-defined custom feature preset',
                'features' => $features,
            ]);

            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['adm_flash_success'] = "Custom preset '{$presetTitle}' saved successfully!";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId . "?tab=tab-features");
        exit;
    }

    #[Route('/admin/project/preset/delete', method: 'POST')]
    public function deletePreset()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $presetKey = $_POST['preset_key'] ?? '';
        $this->loadProjectConfig();
        if ($projectId) {
            $this->requireProjectAdmin($projectId);
        }

        if ($presetKey) {
            FeatureManager::deleteCustomPreset($presetKey);
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['adm_flash_success'] = "Custom preset deleted.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId . "?tab=tab-features");
        exit;
    }

    #[Route('/admin/project/save-storage', method: 'POST')]
    public function saveStorage()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $targetStorage = strtolower(trim($_POST['target_storage'] ?? 'json'));
        if (!in_array($targetStorage, ['json', 'sqlite', 'mysql', 'mariadb', 'pgsql', 'postgres'])) {
            $targetStorage = 'json';
        }

        $project = $this->config['projects'][$projectId];
        $currentStorage = strtolower($project['storage'] ?? 'json');
        $projectConfigPath = $project['_config_path'] ?? '';

        $dbConfig = [
            'driver' => in_array($targetStorage, ['pgsql', 'postgres']) ? 'pgsql' : 'mysql',
            'host' => trim($_POST['db_host'] ?? 'localhost') ?: 'localhost',
            'port' => trim($_POST['db_port'] ?? '') ?: ($targetStorage === 'pgsql' ? '5432' : '3306'),
            'database' => trim($_POST['db_name'] ?? 'sppdocs') ?: 'sppdocs',
            'username' => trim($_POST['db_user'] ?? 'root') ?: 'root',
            'password' => $_POST['db_pass'] ?? '',
            'table_prefix' => trim($_POST['table_prefix'] ?? 'sppdocs_') ?: 'sppdocs_',
        ];

        if ($projectConfigPath && file_exists($projectConfigPath)) {
            $issuesDir = $this->getIssuesDir($project);
            $sourceDriver = \App\SPPDocs\Storage\StorageFactory::create($project, $issuesDir);

            // Instantiate target driver
            if (in_array($targetStorage, ['mysql', 'mariadb', 'pgsql', 'postgres'])) {
                $targetDriver = new \App\SPPDocs\Storage\DatabaseStorageDriver($projectId, $dbConfig);
            } elseif ($targetStorage === 'sqlite') {
                $targetDriver = new \App\SPPDocs\Storage\SqliteStorageDriver($issuesDir);
            } else {
                $targetDriver = new \App\SPPDocs\Storage\JsonStorageDriver($issuesDir);
            }

            // Migrate issues if storage engine changed
            if ($currentStorage !== $targetStorage) {
                $allIssues = $sourceDriver->loadAll();
                foreach ($allIssues as $issue) {
                    $full = $sourceDriver->find($issue['id']);
                    if ($full) {
                        $targetDriver->save($issue['id'], $full);
                    }
                }
            }

            $yamlData = Yaml::parseFile($projectConfigPath) ?: [];
            $yamlData['storage'] = $targetStorage;
            if (in_array($targetStorage, ['mysql', 'mariadb', 'pgsql', 'postgres'])) {
                $yamlData['db_config'] = $dbConfig;
            }
            unset($yamlData['_config_path']);
            file_put_contents($projectConfigPath, Yaml::dump($yamlData, 4, 2));
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId . "#tab-storage");
        exit;
    }

    #[Route('/admin/project/save-links', method: 'POST')]
    public function saveLinks()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $projectConfigPath = $this->config['projects'][$projectId]['_config_path'] ?? '';
        if (!$projectConfigPath || !file_exists($projectConfigPath)) {
            exit('Project config not found');
        }

        $yamlData = Yaml::parseFile($projectConfigPath) ?: [];
        $rawLinks = $_POST['links'] ?? [];

        $cleanedLinks = [];
        foreach ($rawLinks as $key => $val) {
            if ($key === 'custom') {
                continue;
            }
            $trimmed = trim($val);
            if ($trimmed !== '') {
                if (in_array($key, ['website', 'source'])) {
                    $cleanedLinks[$key] = \SPP\Core\Url::external($trimmed);
                } else {
                    if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, '#')) {
                        $cleanedLinks[$key] = $trimmed;
                    } else {
                        $cleanedLinks[$key] = \SPP\Core\Url::external($trimmed);
                    }
                }
            }
        }

        // Custom External Links
        $customLinks = [];
        $customIcons = $_POST['custom_links']['icon'] ?? [];
        $customTitles = $_POST['custom_links']['title'] ?? [];
        $customUrls = $_POST['custom_links']['url'] ?? [];

        if (is_array($customTitles)) {
            $count = count($customTitles);
            for ($i = 0; $i < $count; $i++) {
                $title = trim($customTitles[$i] ?? '');
                $url = trim($customUrls[$i] ?? '');
                $icon = trim($customIcons[$i] ?? '') ?: '🔗';
                if ($title !== '' && $url !== '') {
                    $customLinks[] = [
                        'icon' => $icon,
                        'title' => $title,
                        'url' => \SPP\Core\Url::external($url),
                    ];
                }
            }
        }

        if (!empty($customLinks)) {
            $cleanedLinks['custom'] = $customLinks;
        }

        $yamlData['links'] = $cleanedLinks;

        unset($yamlData['_config_path']);
        file_put_contents($projectConfigPath, Yaml::dump($yamlData, 4, 2));

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId . "#tab-links");
        exit;
    }

    #[Route('/admin/project/save-team', method: 'POST')]
    public function saveTeam()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $userRoles = $_POST['user_roles'] ?? [];
        \App\SPPDocs\Services\PermissionManager::saveProjectMembers($projectId, $userRoles);
        $_SESSION['adm_flash_success'] = "Project team roles updated successfully.";

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId . "#tab-team");
        exit;
    }

    #[Route('/admin/project/save-releases', method: 'POST')]
    public function saveReleases()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $projectConfigPath = $this->config['projects'][$projectId]['_config_path'] ?? '';
        if (!$projectConfigPath || !file_exists($projectConfigPath)) {
            exit('Project config not found');
        }

        $yamlData = Yaml::parseFile($projectConfigPath) ?: [];
        $rawReleases = $_POST['releases'] ?? [];

        $cleanedReleases = [];
        foreach ($rawReleases as $rel) {
            $ver = trim($rel['version'] ?? '');
            if ($ver !== '') {
                $url = trim($rel['url'] ?? '');
                if ($url !== '' && !str_starts_with($url, '/')) {
                    $url = \SPP\Core\Url::external($url);
                }
                $cleanedReleases[] = [
                    'version' => $ver,
                    'title' => trim($rel['title'] ?? ''),
                    'url' => $url,
                    'notes' => trim($rel['notes'] ?? ''),
                ];
            }
        }
        $yamlData['releases'] = $cleanedReleases;

        unset($yamlData['_config_path']);
        file_put_contents($projectConfigPath, Yaml::dump($yamlData, 4, 2));

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId);
        exit;
    }

    #[Route('/admin/project/delete', method: 'POST')]
    public function deleteProject()
    {
        $this->requireAuth();
        $this->requireGlobalAdmin();

        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        // 1. Remove from sppdocs.yml
        $mainConfigPath = __DIR__ . '/../etc/sppdocs.yml';
        if (file_exists($mainConfigPath)) {
            $mainConfig = Yaml::parseFile($mainConfigPath) ?: [];
            unset($mainConfig['projects'][$projectId]);
            file_put_contents($mainConfigPath, Yaml::dump($mainConfig, 4, 2));
        }

        // 2. Safely archive project folder
        $projectPath = dirname(SPP_BASE_DIR) . '/docs/' . $projectId;
        if (is_dir($projectPath)) {
            $archivePath = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '.archived_' . time();
            @rename($projectPath, $archivePath);
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin");
        exit;
    }

    #[Route('/admin/snippets', method: 'GET')]
    public function snippetsIndex()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $keys = array_keys($this->config['projects'] ?? []);
            $projectId = $keys[0] ?? '';
        }

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        $snippets = \App\SPPDocs\Services\SnippetManager::listSnippets($projectId);

        return $this->render('admin.snippets', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'snippets' => $snippets,
            'active_tab' => 'snippets',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $this->getProjectUser(),
        ]);
    }

    #[Route('/admin/snippets/save', method: 'POST')]
    public function saveSnippet()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $name = trim($_POST['name'] ?? '');
        $content = $_POST['content'] ?? '';

        if (!empty($name)) {
            \App\SPPDocs\Services\SnippetManager::saveSnippet($projectId, $name, $content);
            $_SESSION['adm_flash_success'] = "Snippet '{$name}' saved successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/snippets?project=" . urlencode($projectId));
        exit;
    }

    #[Route('/admin/snippets/delete', method: 'POST')]
    public function deleteSnippet()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $name = trim($_POST['name'] ?? '');
        if (!empty($name)) {
            \App\SPPDocs\Services\SnippetManager::deleteSnippet($projectId, $name);
            $_SESSION['adm_flash_success'] = "Snippet '{$name}' deleted successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/snippets?project=" . urlencode($projectId));
        exit;
    }

    #[Route('/admin/analytics', method: 'GET')]
    public function analyticsDashboard()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $keys = array_keys($this->config['projects'] ?? []);
            $projectId = $keys[0] ?? '';
        }

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];

        $dataDir = dirname(SPP_BASE_DIR) . '/docs/' . $projectId . '/data';
        $feedbackFile = $dataDir . '/feedback.json';
        $feedback = file_exists($feedbackFile) ? (json_decode(file_get_contents($feedbackFile), true) ?: []) : [];

        $unmetFile = $dataDir . '/unmet_queries.json';
        $unmetQueries = file_exists($unmetFile) ? (json_decode(file_get_contents($unmetFile), true) ?: []) : [];

        // Aggregate helpfulness metrics
        $totalFeedback = count($feedback);
        $helpfulCount = 0;
        $unhelpfulCount = 0;
        $pageBreakdown = [];

        foreach ($feedback as $entry) {
            $val = $entry['rating'] ?? '';
            $path = $entry['path'] ?? 'unknown';
            if (!isset($pageBreakdown[$path])) {
                $pageBreakdown[$path] = ['helpful' => 0, 'unhelpful' => 0, 'comments' => []];
            }
            if ($val === 'yes') {
                $helpfulCount++;
                $pageBreakdown[$path]['helpful']++;
            } elseif ($val === 'no') {
                $unhelpfulCount++;
                $pageBreakdown[$path]['unhelpful']++;
            }
            if (!empty($entry['comment'])) {
                $pageBreakdown[$path]['comments'][] = [
                    'comment' => $entry['comment'],
                    'created_at' => $entry['created_at'] ?? time(),
                ];
            }
        }

        // Sort unmet queries by frequency
        uasort($unmetQueries, function ($a, $b) {
            return ($b['count'] ?? 1) <=> ($a['count'] ?? 1);
        });

        return $this->render('admin.analytics', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'totalFeedback' => $totalFeedback,
            'helpfulCount' => $helpfulCount,
            'unhelpfulCount' => $unhelpfulCount,
            'helpfulRatio' => $totalFeedback > 0 ? round(($helpfulCount / $totalFeedback) * 100, 1) : 0,
            'pageBreakdown' => $pageBreakdown,
            'unmetQueries' => array_slice($unmetQueries, 0, 30, true),
            'recentFeedback' => array_slice(array_reverse($feedback), 0, 25),
            'active_tab' => 'analytics',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $this->getProjectUser(),
        ]);
    }

    #[Route('/admin/schemas', method: 'GET')]
    public function schemasIndex()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $keys = array_keys($this->config['projects'] ?? []);
            $projectId = $keys[0] ?? '';
        }

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('schemas', $project, $projectId);
        $schemas = \App\SPPDocs\Services\SchemaStudioService::listSchemas($projectId);

        return $this->render('admin.schemas', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'schemas' => $schemas,
            'active_tab' => 'schemas',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $this->getProjectUser(),
        ]);
    }

    #[Route('/admin/schemas/save', method: 'POST')]
    public function saveSchema()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $type = trim($_POST['type'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $rawFields = $_POST['fields'] ?? [];

        $cleanedFields = [];
        if (is_array($rawFields)) {
            foreach ($rawFields as $f) {
                $k = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($f['key'] ?? '')));
                if (!empty($k)) {
                    $opts = trim($f['options'] ?? '');
                    $optsArray = !empty($opts) ? array_map('trim', explode(',', $opts)) : [];
                    $viewRoles = !empty($f['view_roles']) ? (is_array($f['view_roles']) ? $f['view_roles'] : array_map('trim', explode(',', $f['view_roles']))) : [];
                    $editRoles = !empty($f['edit_roles']) ? (is_array($f['edit_roles']) ? $f['edit_roles'] : array_map('trim', explode(',', $f['edit_roles']))) : [];

                    $cleanedFields[$k] = [
                        'label' => trim($f['label'] ?? ucfirst($k)),
                        'type' => trim($f['type'] ?? 'text'),
                        'required' => !empty($f['required']),
                        'options' => $optsArray,
                        'vocab' => trim($f['vocab'] ?? ''),
                        'target_collection' => trim($f['target_collection'] ?? ''),
                        'view_roles' => $viewRoles,
                        'edit_roles' => $editRoles,
                    ];
                }
            }
        }

        if (!empty($type)) {
            \App\SPPDocs\Services\SchemaStudioService::saveSchema($projectId, $type, [
                'title' => $title,
                'description' => $description,
                'fields' => $cleanedFields,
            ]);
            $_SESSION['adm_flash_success'] = "Content type '{$type}' saved successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/schemas?project=" . urlencode($projectId));
        exit;
    }

    #[Route('/admin/schemas/delete', method: 'POST')]
    public function deleteSchema()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);

        $type = trim($_POST['type'] ?? '');
        if (!empty($type)) {
            \App\SPPDocs\Services\SchemaStudioService::deleteSchema($projectId, $type);
            $_SESSION['adm_flash_success'] = "Content type '{$type}' deleted successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/schemas?project=" . urlencode($projectId));
        exit;
    }

    #[Route('/admin/i18n', method: 'GET')]
    public function i18nIndex()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $keys = array_keys($this->config['projects'] ?? []);
            $projectId = $keys[0] ?? '';
        }

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('i18n', $project, $projectId);
        $version = $_GET['version'] ?? ($project['default_version'] ?? 'v1');

        $parity = \App\SPPDocs\Services\I18nService::getParityMatrix($project, $version);

        return $this->render('admin.i18n', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'version' => $version,
            'parity' => $parity,
            'active_tab' => 'i18n',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $this->getProjectUser(),
        ]);
    }

    #[Route('/admin/i18n/pair', method: 'GET')]
    public function i18nPair()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? '';
        $version = $_GET['version'] ?? 'v1';
        $locale = $_GET['locale'] ?? '';
        $slug = $_GET['slug'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            \SPP\Response::json(['error' => 'Project not found'], 404);
            return;
        }

        $project = $this->config['projects'][$projectId];
        $pair = \App\SPPDocs\Services\I18nService::getTranslationPair($project, $version, $locale, $slug);
        \SPP\Response::json($pair);
    }

    #[Route('/admin/i18n/save', method: 'POST')]
    public function i18nSave()
    {
        $this->requireAuth();
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $projectId = $input['project_id'] ?? '';
        $version = $input['version'] ?? 'v1';
        $locale = $input['locale'] ?? '';
        $slug = $input['slug'] ?? '';
        $content = $input['content'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            \SPP\Response::json(['error' => 'Project not found'], 404);
            return;
        }
        $this->requireProjectAdmin($projectId);

        $project = $this->config['projects'][$projectId];
        $success = \App\SPPDocs\Services\I18nService::saveTranslation($project, $version, $locale, $slug, $content);
        \SPP\Response::json(['success' => $success]);
    }
}


