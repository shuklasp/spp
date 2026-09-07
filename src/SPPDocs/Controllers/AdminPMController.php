<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Traits\RBACGuard;

class AdminPMController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait, RBACGuard;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/admin/roles', method: 'GET')]
    public function rolesIndex()
    {
        $this->ensureSession();
        $this->requireGlobalAdmin();

        $roles = \App\SPPDocs\Services\PermissionManager::getAllRoles();
        $permissionsCatalog = \App\SPPDocs\Services\PermissionManager::getAvailablePermissions();
        $allProjects = \App\SPPDocs\Services\PermissionManager::getAllProjectList();
        $allUsers = \App\SPPDocs\Services\UserService::getAllUsers();

        // Detect project context if filtered or launched from a project
        $projectId = trim($_GET['projectId'] ?? $_GET['project'] ?? '');
        $project = null;
        if (!empty($projectId)) {
            if (isset($this->config['projects'][$projectId])) {
                $project = $this->config['projects'][$projectId];
            } elseif (isset($allProjects[$projectId])) {
                $project = ['title' => $allProjects[$projectId]['title'] ?? $projectId];
            } else {
                $projectId = null;
            }
        } else {
            $projectId = null;
        }

        // Calculate member counts and assignments per role across all projects
        $roleUsage = [];
        $roleAssignments = [];
        $roleProjectUsage = [];
        $roleProjectAssignments = [];

        foreach ($roles as $rId => $rDef) {
            $assignments = \App\SPPDocs\Services\PermissionManager::getRoleAssignments($rId);
            $roleAssignments[$rId] = $assignments;
            $roleUsage[$rId] = count($assignments);

            if ($projectId) {
                $projAssigned = array_values(array_filter($assignments, function($a) use ($projectId) {
                    return ($a['project_id'] ?? '') === $projectId;
                }));
                $roleProjectAssignments[$rId] = $projAssigned;
                $roleProjectUsage[$rId] = count($projAssigned);
            }
        }

        return $this->render('admin.roles', [
            'roles' => $roles,
            'permissions_catalog' => $permissionsCatalog,
            'role_usage' => $roleUsage,
            'role_assignments' => $roleAssignments,
            'role_project_usage' => $roleProjectUsage,
            'role_project_assignments' => $roleProjectAssignments,
            'all_projects' => $allProjects,
            'all_users' => $allUsers,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'active_tab' => 'roles',
            'project_id' => $projectId,
            'project' => $project,
        ]);
    }

    #[Route('/admin/roles/assign-member', method: 'POST')]
    public function assignRoleMember()
    {
        $this->verifyCsrf();
        $this->requireGlobalAdmin();

        $roleId = trim($_POST['role_id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $projectId = trim($_POST['project_id'] ?? '');
        $returnProjectId = trim($_POST['return_project_id'] ?? $projectId);

        if ($roleId && $username && $projectId) {
            \App\SPPDocs\Services\PermissionManager::assignUserProjectRole($username, $projectId, $roleId);
            $_SESSION['adm_flash_success'] = "Assigned user @{$username} to role '{$roleId}' in project '{$projectId}'.";
        }

        $redirectUrl = \SPP\App::getBaseUrl() . "/admin/roles";
        if (!empty($returnProjectId)) {
            $redirectUrl .= "?projectId=" . urlencode($returnProjectId);
        }
        header("Location: " . $redirectUrl);
        exit;
    }

    #[Route('/admin/roles/save', method: 'POST')]
    public function saveRole()
    {
        $this->verifyCsrf();
        $this->requireGlobalAdmin();

        $returnProjectId = trim($_POST['return_project_id'] ?? $_POST['project_id'] ?? '');
        $redirectUrl = \SPP\App::getBaseUrl() . "/admin/roles";
        if (!empty($returnProjectId)) {
            $redirectUrl .= "?projectId=" . urlencode($returnProjectId);
        }

        // Backward compatibility for legacy project-level save
        if (isset($_POST['user_roles']) && isset($_POST['project_id'])) {
            $projectId = $_POST['project_id'];
            $userRoles = $_POST['user_roles'];
            \App\SPPDocs\Services\PermissionManager::saveProjectMembers($projectId, $userRoles);
            header("Location: " . \SPP\App::getBaseUrl() . "/admin/project/" . $projectId . "#tab-team");
            exit;
        }

        $roleId = trim($_POST['role_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $permissions = (array)($_POST['permissions'] ?? []);

        if (empty($roleId) || empty($name)) {
            $_SESSION['adm_flash_error'] = 'Role Identifier and Role Name are required.';
            header("Location: " . $redirectUrl);
            exit;
        }

        \App\SPPDocs\Services\PermissionManager::saveRole($roleId, $name, $description, $permissions);

        $_SESSION['adm_flash_success'] = "Role '{$name}' saved successfully.";
        header("Location: " . $redirectUrl);
        exit;
    }

    #[Route('/admin/roles/delete', method: 'POST')]
    public function deleteRole()
    {
        $this->verifyCsrf();
        $this->requireGlobalAdmin();

        $returnProjectId = trim($_POST['return_project_id'] ?? $_POST['project_id'] ?? '');
        $redirectUrl = \SPP\App::getBaseUrl() . "/admin/roles";
        if (!empty($returnProjectId)) {
            $redirectUrl .= "?projectId=" . urlencode($returnProjectId);
        }

        $roleId = trim($_POST['role_id'] ?? '');
        if (empty($roleId)) {
            header("Location: " . $redirectUrl);
            exit;
        }

        $success = \App\SPPDocs\Services\PermissionManager::deleteRole($roleId);
        if ($success) {
            $_SESSION['adm_flash_success'] = "Role '{$roleId}' deleted successfully.";
        } else {
            $_SESSION['adm_flash_error'] = "System role '{$roleId}' cannot be deleted.";
        }

        header("Location: " . $redirectUrl);
        exit;
    }

    // =========================================================================
    //  AUTOMATION RULES
    // =========================================================================

    #[Route('/admin/automations', method: 'GET')]
    public function automationsIndex()
    {
        $projectId = $_GET['projectId'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $project = $this->config['projects'][$projectId];
        $this->ensureSession();
        $this->requireProjectAdmin($projectId);
        \App\SPPDocs\Services\FeatureManager::requireFeature('automations', $project, $projectId);
        $issuesDir = $this->getIssuesDir($project);
        $user = $this->getProjectUser();

        $rulesFile = $issuesDir . '/automations.json';
        $rules = file_exists($rulesFile) ? (json_decode(file_get_contents($rulesFile), true) ?: []) : [];

        return $this->render('admin.automations', [
            'project' => $project,
            'project_id' => $projectId,
            'rules' => $rules,
            'user' => $user,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'active_tab' => 'automations',
        ]);
    }

    #[Route('/admin/automations/save', method: 'POST')]
    public function saveAutomation()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $this->requireProjectAdmin($projectId);

        $issuesDir = $this->getIssuesDir($project);
        $rulesFile = $issuesDir . '/automations.json';
        $rules = file_exists($rulesFile) ? (json_decode(file_get_contents($rulesFile), true) ?: []) : [];

        $ruleId = $_POST['rule_id'] ?? ('rule_' . time() . '_' . bin2hex(random_bytes(3)));
        $rules[$ruleId] = [
            'name' => strip_tags($_POST['rule_name'] ?? 'Unnamed Rule'),
            'trigger_event' => $_POST['trigger_event'] ?? 'issue.created',
            'condition_field' => $_POST['condition_field'] ?? '',
            'condition_value' => $_POST['condition_value'] ?? '',
            'action_type' => $_POST['action_type'] ?? 'assign',
            'action_value' => $_POST['action_value'] ?? '',
            'enabled' => !empty($_POST['enabled']),
        ];

        file_put_contents($rulesFile, json_encode($rules, JSON_PRETTY_PRINT));

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/automations?projectId=$projectId");
        exit;
    }

    #[Route('/admin/automations/delete', method: 'POST')]
    public function deleteAutomation()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $ruleId = $_POST['rule_id'] ?? '';
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $this->requireProjectAdmin($projectId);

        $issuesDir = $this->getIssuesDir($project);
        $rulesFile = $issuesDir . '/automations.json';
        $rules = file_exists($rulesFile) ? (json_decode(file_get_contents($rulesFile), true) ?: []) : [];
        unset($rules[$ruleId]);
        file_put_contents($rulesFile, json_encode($rules, JSON_PRETTY_PRINT));

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/automations?projectId=$projectId");
        exit;
    }

    // =========================================================================
    //  WEBHOOKS (Outbound)
    // =========================================================================

    #[Route('/admin/webhooks', method: 'GET')]
    public function webhooksIndex()
    {
        $projectId = $_GET['projectId'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found']);
        }

        $project = $this->config['projects'][$projectId];
        $this->ensureSession();
        $this->requireProjectAdmin($projectId);
        \App\SPPDocs\Services\FeatureManager::requireFeature('webhooks', $project, $projectId);
        $issuesDir = $this->getIssuesDir($project);
        $user = $this->getProjectUser();

        $hooksFile = $issuesDir . '/webhooks.json';
        $hooks = file_exists($hooksFile) ? (json_decode(file_get_contents($hooksFile), true) ?: []) : [];

        return $this->render('admin.webhooks', [
            'project' => $project,
            'project_id' => $projectId,
            'hooks' => $hooks,
            'user' => $user,
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'active_tab' => 'webhooks',
        ]);
    }

    #[Route('/admin/webhooks/save', method: 'POST')]
    public function saveWebhook()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $this->requireProjectAdmin($projectId);

        $issuesDir = $this->getIssuesDir($project);
        $hooksFile = $issuesDir . '/webhooks.json';
        $hooks = file_exists($hooksFile) ? (json_decode(file_get_contents($hooksFile), true) ?: []) : [];

        $hookId = $_POST['hook_id'] ?? ('hook_' . bin2hex(random_bytes(4)));
        $events = $_POST['events'] ?? [];
        if (is_string($events)) {
            $events = array_filter(array_map('trim', explode(',', $events)));
        }
        if (empty($events)) {
            $events = ['issue.created', 'issue.closed'];
        }

        $rawUrl = trim($_POST['webhook_url'] ?? '');
        $url = !empty($rawUrl) ? \SPP\Core\Url::external($rawUrl) : '';

        if (!empty($url)) {
            try {
                \App\SPPDocs\Services\SSRFGuard::assertSafeUrl($url);
            } catch (\InvalidArgumentException $e) {
                $_SESSION['sppdocs_flash_error'] = 'SSRF Guard: ' . $e->getMessage();
                header("Location: " . \SPP\App::getBaseUrl() . "/admin/webhooks?projectId=$projectId");
                exit;
            }
        }

        $hooks[$hookId] = [
            'url' => $url,
            'events' => array_values($events),
            'secret' => trim($_POST['webhook_secret'] ?? '') ?: bin2hex(random_bytes(16)),
            'enabled' => !empty($_POST['enabled']),
        ];

        file_put_contents($hooksFile, json_encode($hooks, JSON_PRETTY_PRINT));

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/webhooks?projectId=$projectId");
        exit;
    }

    #[Route('/admin/webhooks/delete', method: 'POST')]
    public function deleteWebhook()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $hookId = $_POST['hook_id'] ?? '';
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $this->requireProjectAdmin($projectId);

        $issuesDir = $this->getIssuesDir($project);
        $hooksFile = $issuesDir . '/webhooks.json';
        $hooks = file_exists($hooksFile) ? (json_decode(file_get_contents($hooksFile), true) ?: []) : [];
        unset($hooks[$hookId]);
        file_put_contents($hooksFile, json_encode($hooks, JSON_PRETTY_PRINT));

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/webhooks?projectId=$projectId");
        exit;
    }
}
