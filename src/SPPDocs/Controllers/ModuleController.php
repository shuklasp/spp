<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\ModuleManager;
use App\SPPDocs\Services\PermissionManager;
use SPP\Response;

class ModuleController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/admin/modules', method: 'GET')]
    public function adminIndex()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? 'spp';
        $project = $this->config['projects'][$projectId] ?? ['title' => ucfirst($projectId)];

        $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';
        if (!PermissionManager::isProjectAdmin($projectId, $currentUser)) {
            http_response_code(403);
            return $this->render('errors/403', ['message' => 'Unauthorized: Admin access required to manage modules.']);
        }
        \App\SPPDocs\Services\FeatureManager::requireFeature('modules', $project, $projectId);

        $allModules = ModuleManager::listAvailableModules($projectId);

        $categories = [];
        foreach ($allModules as $mod) {
            $cat = $mod['category'] ?? 'General';
            $categories[$cat][] = $mod;
        }

        return $this->render('admin.modules', [
            'project_id' => $projectId,
            'project' => $project,
            'modules' => $allModules,
            'categories' => $categories,
            'active_tab' => 'modules',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
        ]);
    }

    #[Route('/admin/modules/toggle', method: 'POST')]
    public function toggle()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? $_GET['project'] ?? 'spp';
        $moduleId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['module_id'] ?? '');
        $enable = !empty($_POST['enable']);

        $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';
        if (!PermissionManager::isProjectAdmin($projectId, $currentUser)) {
            Response::json(['error' => 'Unauthorized'], 403);
            return;
        }

        if (empty($moduleId)) {
            Response::json(['error' => 'Module ID is required'], 400);
            return;
        }

        if ($enable) {
            ModuleManager::enableModule($projectId, $moduleId);
            $msg = "Module '{$moduleId}' successfully enabled.";
        } else {
            ModuleManager::disableModule($projectId, $moduleId);
            $msg = "Module '{$moduleId}' successfully disabled.";
        }

        Response::json([
            'success' => true,
            'module_id' => $moduleId,
            'enabled' => $enable,
            'message' => $msg
        ]);
    }
}