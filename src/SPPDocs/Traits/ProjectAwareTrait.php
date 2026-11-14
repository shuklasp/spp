<?php

namespace App\SPPDocs\Traits;

use Symfony\Component\Yaml\Yaml;

/**
 * Shared trait for all SPPDocs controllers that need project config loading,
 * user authentication, CSRF verification, and session management.
 */
trait ProjectAwareTrait
{
    protected array $config = ['projects' => []];
    protected ?array $project = null;

    protected function safeParseYaml(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        $raw = @file_get_contents($filePath);
        if ($raw === false || $raw === '') {
            return [];
        }
        try {
            return (array)Yaml::parse($raw);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function loadProjectConfig(): void
    {
        $configFile = __DIR__ . '/../etc/sppdocs.yml';
        $this->config = ['projects' => []];
        $baseDir = defined('APP_BASE_DIR') ? APP_BASE_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : getcwd());
        $normBase = str_replace('\\', '/', $baseDir);

        if (file_exists($configFile)) {
            $mainConfig = $this->safeParseYaml($configFile);
            if (isset($mainConfig['projects']) && is_array($mainConfig['projects'])) {
                foreach ($mainConfig['projects'] as $id => $projectConfigPath) {
                    $normPath = str_replace('\\', '/', (string)$projectConfigPath);
                    $normPath = str_ireplace($normBase . '/', '', $normPath);

                    if (!str_starts_with($normPath, '/') && !str_contains($normPath, ':')) {
                        $resolvedPath = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($normPath, '/'));
                    } else {
                        $resolvedPath = $normPath;
                    }

                    if (file_exists($resolvedPath)) {
                        $parsed = $this->safeParseYaml($resolvedPath);
                        $parsed['_config_path'] = $resolvedPath;
                        $this->config['projects'][$id] = $this->normalizeProjectMetadata($id, $parsed);
                    }
                }
            }
        }

        // Auto-discover dynamic/gitignored projects stored in data/projects/*/project.yml
        $dataProjectsDir = __DIR__ . '/../data/projects';
        if (is_dir($dataProjectsDir)) {
            $entries = scandir($dataProjectsDir);
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || !is_dir($dataProjectsDir . '/' . $entry)) {
                    continue;
                }
                $dynYaml = $dataProjectsDir . '/' . $entry . '/project.yml';
                if (file_exists($dynYaml) && !isset($this->config['projects'][$entry])) {
                    $parsed = $this->safeParseYaml($dynYaml);
                    $parsed['_config_path'] = $dynYaml;
                    $this->config['projects'][$entry] = $this->normalizeProjectMetadata($entry, $parsed);
                }
            }
        }
    }

    /**
     * Standardizes project metadata with defaults conforming to SatyaLab project model.
     */
    protected function normalizeProjectMetadata(string $id, array $data): array
    {
        $data['id'] = $id;
        $data['type'] = strtolower($data['type'] ?? 'docs'); // 'docs' | 'app' | 'module'
        $data['visibility'] = strtolower($data['visibility'] ?? 'public'); // 'public' | 'personal' | 'group'
        $data['creator'] = $data['creator'] ?? 'admin';
        $data['is_archived'] = !empty($data['is_archived']) || ($data['status'] ?? '') === 'archived';
        $data['status'] = $data['is_archived'] ? 'archived' : ($data['status'] ?? 'active');
        $data['is_featured'] = !empty($data['is_featured']) || !empty($data['moderation']['is_featured']);
        $data['demo_url'] = $data['demo_url'] ?? ($data['links']['website'] ?? '');
        $data['repo_url'] = $data['repo_url'] ?? ($data['links']['source'] ?? '');
        $data['package_name'] = $data['package_name'] ?? '';
        $data['members'] = $data['members'] ?? [];
        $data['moderation'] = $data['moderation'] ?? [];
        return $data;
    }

    /**
     * Determine if a given user can view a specific project based on its visibility and moderation status.
     */
    public function canUserViewProject(string $projectId, array $project, ?string $username = null): bool
    {
        $isGlobalAdmin = $username ? \App\SPPDocs\Services\PermissionManager::isGlobalAdmin($username) : false;
        if ($isGlobalAdmin) {
            return true; // Global admin can see everything
        }

        // Suspended projects are hidden from public and non-admin/non-creator users
        $status = strtolower($project['status'] ?? 'active');
        if ($status === 'suspended') {
            $isCreator = $username && strcasecmp($project['creator'] ?? '', $username) === 0;
            return $isCreator;
        }

        $visibility = strtolower($project['visibility'] ?? 'public');
        if ($visibility === 'personal') {
            if (!$username) {
                return false; // Guests cannot view personal projects
            }
            return strcasecmp($project['creator'] ?? '', $username) === 0;
        }

        if ($visibility === 'group' || $visibility === 'closed_group') {
            if (!$username) {
                return false; // Guests cannot view closed group projects
            }
            $isCreator = strcasecmp($project['creator'] ?? '', $username) === 0;
            if ($isCreator) {
                return true;
            }
            // Check members/team list
            if (isset($project['members'][$username])) {
                return true;
            }
            $team = (array)($project['team'] ?? []);
            foreach ($team as $member) {
                if (is_string($member) && strcasecmp($member, $username) === 0) {
                    return true;
                }
                if (is_array($member) && strcasecmp($member['user'] ?? ($member['username'] ?? ''), $username) === 0) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Determine if a user has management/configuration authority over a project.
     * Creators, designated project admins, and Global Admins have management rights.
     */
    public function canUserManageProject(string $projectId, array $project, ?string $username = null): bool
    {
        if (!$username) {
            return false;
        }
        if (\App\SPPDocs\Services\PermissionManager::isGlobalAdmin($username)) {
            return true;
        }
        if (strcasecmp($project['creator'] ?? '', $username) === 0) {
            return true;
        }
        if (\App\SPPDocs\Services\PermissionManager::isProjectAdmin($projectId, $username)) {
            return true;
        }
        if (!empty($project['members'][$username])) {
            $role = is_array($project['members'][$username]) ? ($project['members'][$username][0] ?? '') : $project['members'][$username];
            if (in_array(strtolower($role), ['admin', 'owner', 'maintainer'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolves a flexible project identifier (e.g. 'demo app', 'DEMO-APP', 'My Demo App', 'demo-app')
     * to the canonical project key registered in sppdocs.yml.
     */
    public function findProjectId(?string $projectId): ?string
    {
        if (empty($this->config['projects'])) {
            $this->loadProjectConfig();
        }

        if ($projectId === null || trim($projectId) === '') {
            return null;
        }

        $trimmed = trim($projectId, " \t\n\r\0\x0B/");
        if ($trimmed === '') {
            return null;
        }

        // 1. Direct exact match
        if (isset($this->config['projects'][$trimmed])) {
            return $trimmed;
        }

        // 2. Direct case-insensitive key match
        foreach ($this->config['projects'] as $key => $proj) {
            if (strcasecmp($key, $trimmed) === 0) {
                return $key;
            }
        }

        // 3. Slugified comparison (replaces spaces, underscores, and special characters with hyphens)
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $trimmed), '-'));
        if ($slug !== '') {
            if (isset($this->config['projects'][$slug])) {
                return $slug;
            }
            foreach ($this->config['projects'] as $key => $proj) {
                $keySlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $key), '-'));
                if ($keySlug === $slug) {
                    return $key;
                }
            }
        }

        // 4. Project title match
        foreach ($this->config['projects'] as $key => $proj) {
            if (!empty($proj['title'])) {
                if (strcasecmp($proj['title'], $trimmed) === 0) {
                    return $key;
                }
                $titleSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $proj['title']), '-'));
                if ($slug !== '' && $titleSlug === $slug) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Resolves the active project ID from parameter, query string, session, or default.
     * Prevents 404s when navigation links omit the projectId query parameter or pass slug variations.
     */
    protected function resolveActiveProjectId(?string $projectId = null): ?string
    {
        if (empty($this->config['projects'])) {
            $this->loadProjectConfig();
        }

        $projectId = $projectId ?? $_GET['projectId'] ?? $_GET['project'] ?? null;

        // 1. Explicit and valid: persist to session and return
        if ($projectId) {
            $matched = $this->findProjectId($projectId);
            if ($matched) {
                if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                    @session_start();
                }
                $_SESSION['sppdocs_current_project'] = $matched;
                $this->project = $this->config['projects'][$matched] ?? null;
                return $matched;
            }
        }

        // 2. Session fallback
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $sessionProj = $_SESSION['sppdocs_current_project'] ?? null;
        if ($sessionProj) {
            $matched = $this->findProjectId($sessionProj);
            if ($matched) {
                $this->project = $this->config['projects'][$matched] ?? null;
                return $matched;
            }
        }

        // 2b. Contextual URI fallback (e.g., /vshiksha/* or /lekhak/* routes)
        $reqUri = $_SERVER['REQUEST_URI'] ?? ($_GET['q'] ?? '');
        if (str_contains($reqUri, 'vshiksha') && isset($this->config['projects']['vshiksha'])) {
            $_SESSION['sppdocs_current_project'] = 'vshiksha';
            $this->project = $this->config['projects']['vshiksha'] ?? null;
            return 'vshiksha';
        }
        if (str_contains($reqUri, 'lekhak') && isset($this->config['projects']['lekhak'])) {
            $_SESSION['sppdocs_current_project'] = 'lekhak';
            $this->project = $this->config['projects']['lekhak'] ?? null;
            return 'lekhak';
        }

        // 3. Demo App fallback
        if (isset($this->config['projects']['demo-app'])) {
            $_SESSION['sppdocs_current_project'] = 'demo-app';
            $this->project = $this->config['projects']['demo-app'] ?? null;
            return 'demo-app';
        }

        // 4. First configured project
        if (!empty($this->config['projects'])) {
            $firstKey = array_key_first($this->config['projects']);
            $_SESSION['sppdocs_current_project'] = $firstKey;
            $this->project = $this->config['projects'][$firstKey] ?? null;
            return $firstKey;
        }

        return null;
    }

    /**
     * Resolves the active user role/persona across the ecosystem:
     * student, teacher, parent, admin, or all.
     * Persists across navigation via session and cookie.
     */
    public function getActiveRole(): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $role = $_GET['role'] ?? ($_GET['as'] ?? ($_COOKIE['sppdocs_active_role'] ?? ($_SESSION['sppdocs_active_role'] ?? null)));

        if ($role) {
            $roleLower = strtolower(trim((string)$role));
            $aliases = [
                'faculty' => 'teacher',
                'instructor' => 'teacher',
                'guardian' => 'parent',
                'father' => 'parent',
                'mother' => 'parent',
                'principal' => 'admin',
                'superadmin' => 'admin',
                'manager' => 'admin',
            ];
            $normalized = $aliases[$roleLower] ?? $roleLower;

            if (in_array($normalized, ['student', 'teacher', 'parent', 'admin', 'all'], true)) {
                $_SESSION['sppdocs_active_role'] = $normalized;
                if (!headers_sent()) {
                    @setcookie('sppdocs_active_role', $normalized, time() + 86400 * 30, '/');
                }
                return $normalized;
            }
        }

        // Fallback to user account role if logged in
        $user = $this->getProjectUser();
        if (!empty($user['role'])) {
            $uRole = strtolower(trim($user['role']));
            if (in_array($uRole, ['student', 'teacher', 'parent', 'admin'], true)) {
                $_SESSION['sppdocs_active_role'] = $uRole;
                return $uRole;
            }
        }

        return $_SESSION['sppdocs_active_role'] ?? 'admin';
    }

    protected function resolveProjectIdFromProject($project): string
    {
        if (is_string($project) && trim($project) !== '') {
            return trim($project);
        }
        if (is_array($project)) {
            if (!empty($project['id'])) return (string)$project['id'];
            if (!empty($project['slug'])) return (string)$project['slug'];
            if (!empty($this->config['projects'])) {
                foreach ($this->config['projects'] as $pId => $pCfg) {
                    if (is_array($pCfg) && isset($pCfg['_config_path'], $project['_config_path']) && $pCfg['_config_path'] === $project['_config_path']) {
                        return (string)$pId;
                    }
                }
            }
            if (!empty($project['_config_path'])) {
                $base = basename(dirname($project['_config_path']));
                return $base === 'docs' ? basename(dirname(dirname($project['_config_path']))) : $base;
            }
        }
        return 'global';
    }

    protected function getIssuesDir($project): string
    {
        $projectId = $this->resolveProjectIdFromProject($project);
        $projectArr = is_array($project) ? $project : ($this->config['projects'][$projectId] ?? null);
        return \App\SPPDocs\Services\DataStorageResolver::getIssuesDir($projectId, $projectArr);
    }

    protected function getForumsDir($project): string
    {
        $projectId = $this->resolveProjectIdFromProject($project);
        $projectArr = is_array($project) ? $project : ($this->config['projects'][$projectId] ?? null);
        return \App\SPPDocs\Services\DataStorageResolver::getForumsDir($projectId, $projectArr);
    }

    protected function getDataDir(?string $projectId = null): string
    {
        $pId = $projectId ?? $this->resolveProjectIdFromProject($this->project ?? []);
        $projectArr = is_array($this->project) ? $this->project : ($this->config['projects'][$pId] ?? null);
        return \App\SPPDocs\Services\DataStorageResolver::getProjectDataDir($pId, $projectArr);
    }

    protected function getProjectUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (isset($_SESSION['sppdocs_user'])) {
            return [
                'username' => $_SESSION['sppdocs_user'],
                'role' => $_SESSION['sppdocs_role'] ?? 'admin'
            ];
        }

        if (isset($_SESSION['store_publisher'])) {
            return [
                'username' => $_SESSION['store_publisher'],
                'role' => $_SESSION['store_role'] ?? 'publisher'
            ];
        }

        if (isset($_SESSION['auth_user'])) {
            return [
                'username' => is_array($_SESSION['auth_user']) ? ($_SESSION['auth_user']['username'] ?? 'User') : (string)$_SESSION['auth_user'],
                'role' => 'user'
            ];
        }

        if (class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
            $guard = \SPPMod\SPPAuth\SPPAuth::guard('web');
            $user = $guard->user();
            return [
                'username' => $user->username ?? $user->name ?? 'User',
                'role' => 'registered'
            ];
        }

        return null;
    }

    protected function isUserAdmin(): bool
    {
        $this->ensureSession();
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;
        if (!$username) {
            return false;
        }

        if (\App\SPPDocs\Services\PermissionManager::isGlobalAdmin($username)) {
            return true;
        }

        if (empty($this->config['projects'])) {
            $this->loadProjectConfig();
        }
        foreach ($this->config['projects'] as $pId => $pCfg) {
            if (\App\SPPDocs\Services\PermissionManager::isProjectAdmin($pId, $username)) {
                return true;
            }
        }

        $authMode = \SPP\SPPConfig::get('sppdocs.auth_mode') ?? 'flatfile';
        if ($authMode === 'sppauth') {
            return class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::authSessionExists() && (\SPPMod\SPPAuth\SPPAuth::hasRight('sppdocs_admin') || \SPPMod\SPPAuth\SPPAuth::hasRight('admin'));
        }

        return isset($_SESSION['sppdocs_admin_auth']);
    }

    protected function requireAuth(): void
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

    protected function verifyCsrf(): void
    {
        $submittedToken = $_POST['csrf_token'] ?? $_REQUEST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = class_exists('\SPP\SPPSession') ? \SPP\SPPSession::getCsrfToken() : ($_SESSION['sppdocs_csrf'] ?? '');

        $isValid = false;
        if (!empty($submittedToken)) {
            if (!empty($sessionToken) && hash_equals($sessionToken, $submittedToken)) {
                $isValid = true;
            } elseif (!empty($_SESSION['sppdocs_csrf']) && hash_equals($_SESSION['sppdocs_csrf'], $submittedToken)) {
                $isValid = true;
            }
        }

        if (!$isValid) {
            http_response_code(403);
            exit('CSRF token mismatch or expired. Please refresh the page.');
        }
    }

    protected function ensureSession(): void
    {
        if (class_exists('\SPP\SPPSession')) {
            $token = \SPP\SPPSession::getCsrfToken();
            $_SESSION['sppdocs_csrf'] = $token;
        } else {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }
            if (empty($_SESSION['sppdocs_csrf'])) {
                $_SESSION['sppdocs_csrf'] = bin2hex(random_bytes(32));
            }
        }
    }

    protected function getRegisteredUsers(): array
    {
        $users = [];
        $usersFile = dirname(SPP_BASE_DIR) . '/spp/data/xdb/users/users.xml';
        if (file_exists($usersFile)) {
            $xml = @simplexml_load_file($usersFile);
            if ($xml) {
                foreach ($xml->row as $row) {
                    $users[] = (string) $row->username;
                }
            }
        }
        if (empty($users)) {
            $users[] = 'admin';
        }
        return array_unique($users);
    }

    protected function loadAllIssues($issuesDir, ?array $project = null): array
    {
        $proj = $project ?? $this->project ?? [];
        if (!empty($proj)) {
            return \App\SPPDocs\Storage\StorageFactory::create($proj, $issuesDir)->loadAll();
        }
        return (new \App\SPPDocs\Storage\JsonStorageDriver($issuesDir))->loadAll();
    }

    protected function loadIssue($issuesDir, $issueId, ?array $project = null): ?array
    {
        $proj = $project ?? $this->project ?? [];
        if (!empty($proj)) {
            return \App\SPPDocs\Storage\StorageFactory::create($proj, $issuesDir)->find($issueId);
        }
        return (new \App\SPPDocs\Storage\JsonStorageDriver($issuesDir))->find($issueId);
    }

    protected function saveIssue($issuesDir, $issueId, array $data, ?array $project = null): void
    {
        $proj = $project ?? $this->project ?? [];
        if (!empty($proj)) {
            \App\SPPDocs\Storage\StorageFactory::create($proj, $issuesDir)->save($issueId, $data);
            return;
        }
        (new \App\SPPDocs\Storage\JsonStorageDriver($issuesDir))->save($issueId, $data);
    }

    protected function loadTeams($issuesDir): array
    {
        $teamsFile = $issuesDir . '/teams.json';
        if (file_exists($teamsFile)) {
            return json_decode(file_get_contents($teamsFile), true) ?: [];
        }
        return [];
    }

    protected function saveTeams($issuesDir, array $teams): void
    {
        $teamsFile = $issuesDir . '/teams.json';
        $fp = fopen($teamsFile, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($teams, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    protected function loadMilestones($issuesDir): array
    {
        $file = $issuesDir . '/milestones.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    protected function saveMilestones($issuesDir, array $milestones): void
    {
        $file = $issuesDir . '/milestones.json';
        $fp = fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($milestones, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    protected function logActivity($issuesDir, string $type, string $actor, string $targetId, string $summary): void
    {
        // 1. Immutable Event Sourcing via SPP CQRS EventStore
        if (class_exists('\SPPMod\SPPWorkflow\CQRS\EventStore')) {
            try {
                \SPPMod\SPPWorkflow\CQRS\EventStore::append('sppdocs_issue', $targetId, $type, [
                    'actor' => $actor,
                    'summary' => $summary,
                ]);
            } catch (\Throwable $e) {}
        }

        // 2. Dual Event Bus dispatch (events and hooks)
        if (class_exists('\SPP\SPPEvent')) {
            try {
                \SPP\SPPEvent::fireEvent('sppdocs.' . $type, [
                    'target_id' => $targetId,
                    'actor' => $actor,
                    'summary' => $summary,
                    'issues_dir' => $issuesDir,
                ]);
                \SPP\SPPEvent::triggerHook('sppdocs:' . $type, [
                    'target_id' => $targetId,
                    'actor' => $actor,
                    'summary' => $summary,
                ]);
            } catch (\Throwable $e) {}
        }

        // 3. Cryptographic Tamper-Evident SHA-256 Hash Chained Audit Trail
        try {
            \App\SPPDocs\Services\AuditLogService::append($issuesDir, $type, $actor, $targetId, $summary);
        } catch (\Throwable $e) {}

        // 4. Backward-compatible append-only log in project issues dir
        $logFile = $issuesDir . '/activity_log.jsonl';
        $entry = json_encode([
            'type' => $type,
            'actor' => $actor,
            'target' => $targetId,
            'summary' => $summary,
            'timestamp' => time(),
        ]) . "\n";
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    protected function authorize(string $permission, ?array $project = null): void
    {
        $project = $project ?? ($this->config['projects'][$_GET['projectId'] ?? ($this->projectId ?? '')] ?? []);
        $projectId = $project['id'] ?? ($_GET['projectId'] ?? ($this->projectId ?? ''));
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;
        $role = \App\SPPDocs\Services\PermissionManager::getProjectUserRole($projectId, $username);
        if (!\App\SPPDocs\Services\PermissionManager::can($permission, $role)) {
            http_response_code(403);
            exit("Access Denied: Your current role ('{$role}') does not have the '{$permission}' permission.");
        }
    }

    protected function requireGlobalAdmin(): void
    {
        $this->ensureSession();
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;

        if (!\App\SPPDocs\Services\PermissionManager::isGlobalAdmin($username)) {
            http_response_code(403);
            exit('Access Denied: Global Super Administrator privileges required.');
        }
    }

    protected function requireProjectAdmin(string $projectId): void
    {
        $this->ensureSession();
        $user = $this->getProjectUser();
        $username = $user['username'] ?? null;
        if (empty($this->config['projects'])) {
            $this->loadProjectConfig();
        }
        $project = $this->config['projects'][$projectId] ?? [];

        if (!$this->canUserManageProject($projectId, $project, $username)) {
            http_response_code(403);
            exit("Access Denied: You do not have management authority over project '{$projectId}'.");
        }
    }

    protected function buildIssueTree(array $issues): array
    {
        $map = [];
        foreach ($issues as &$issue) {
            $issue['children'] = [];
            $map[$issue['id']] = &$issue;
        }
        unset($issue);

        $tree = [];
        foreach ($map as $id => &$issue) {
            $parentId = $issue['parent_id'] ?? null;
            if ($parentId && isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$issue;
            } else {
                $tree[] = &$issue;
            }
        }
        unset($issue);
        return $tree;
    }
}
