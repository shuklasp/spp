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

    protected function loadProjectConfig(): void
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

    /**
     * Resolves the active project ID from parameter, query string, session, or default.
     * Prevents 404s when navigation links omit the projectId query parameter.
     */
    protected function resolveActiveProjectId(?string $projectId = null): ?string
    {
        $projectId = $projectId ?? $_GET['projectId'] ?? $_GET['project'] ?? null;

        // 1. Explicit and valid: persist to session and return
        if ($projectId && isset($this->config['projects'][$projectId])) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }
            $_SESSION['sppdocs_current_project'] = $projectId;
            return $projectId;
        }

        // 2. Session fallback
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $sessionProj = $_SESSION['sppdocs_current_project'] ?? null;
        if ($sessionProj && isset($this->config['projects'][$sessionProj])) {
            return $sessionProj;
        }

        // 3. Demo App fallback
        if (isset($this->config['projects']['demo-app'])) {
            $_SESSION['sppdocs_current_project'] = 'demo-app';
            return 'demo-app';
        }

        // 4. First configured project
        if (!empty($this->config['projects'])) {
            $firstKey = array_key_first($this->config['projects']);
            $_SESSION['sppdocs_current_project'] = $firstKey;
            return $firstKey;
        }

        return null;
    }

    protected function getIssuesDir($project): string
    {
        $dir = $project['issues_dir'] ?? dirname($project['_config_path']) . '/issues';
        if (strpos($dir, 'C:/') === 0 || strpos($dir, 'c:/') === 0) {
            $dir = str_replace(['C:/projects/apache/school1/', 'c:/projects/apache/school1/'], '', $dir);
        }
        if (!str_starts_with($dir, '/') && !str_contains($dir, ':\\')) {
            $dir = SPP_BASE_DIR . '/' . $dir;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
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

        if (!\App\SPPDocs\Services\PermissionManager::isProjectAdmin($projectId, $username)) {
            http_response_code(403);
            exit("Access Denied: Administrator privileges for project '{$projectId}' required.");
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
