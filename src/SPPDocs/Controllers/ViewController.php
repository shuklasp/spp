<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\ViewStudioService;
use App\SPPDocs\Services\SchemaStudioService;
use SPP\Response;

class ViewController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/admin/views', method: 'GET')]
    public function adminIndex()
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
        \App\SPPDocs\Services\FeatureManager::requireFeature('views', $project, $projectId);

        $views = ViewStudioService::listViews($projectId);
        $schemas = SchemaStudioService::listSchemas($projectId);

        return $this->render('admin.views', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'views' => $views,
            'schemas' => $schemas,
            'active_tab' => 'views',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $this->getProjectUser(),
        ]);
    }

    #[Route('/admin/views/save', method: 'POST')]
    public function save()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('views', $project, $projectId);

        $viewId = trim($_POST['view_id'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $collection = trim($_POST['collection'] ?? 'page');
        $displayFormat = trim($_POST['display_format'] ?? 'grid');
        $limit = (int)($_POST['limit'] ?? 10);
        $filters = $_POST['filters'] ?? [];
        $sorts = $_POST['sorts'] ?? [];

        if (!empty($viewId)) {
            ViewStudioService::saveView($projectId, $viewId, [
                'title' => $title,
                'collection' => $collection,
                'display_format' => $displayFormat,
                'limit' => $limit,
                'filters' => $filters,
                'sorts' => $sorts,
            ]);
            $_SESSION['adm_flash_success'] = "Dynamic View '{$viewId}' saved successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/views?project=" . urlencode($projectId));
        exit;
    }

    #[Route('/admin/views/delete', method: 'POST')]
    public function delete()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('views', $project, $projectId);

        $viewId = trim($_POST['view_id'] ?? '');
        if (!empty($viewId)) {
            ViewStudioService::deleteView($projectId, $viewId);
            $_SESSION['adm_flash_success'] = "View '{$viewId}' deleted successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/views?project=" . urlencode($projectId));
        exit;
    }

    #[Route('/admin/views/preview', method: 'GET')]
    public function preview()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? '';
        $viewId = $_GET['view_id'] ?? '';

        $result = ViewStudioService::executeView($projectId, $viewId, $_GET);
        Response::json($result);
    }

    #[Route('/project/{projectId}/views/{viewId}', method: 'GET')]
    public function show($projectId, $viewId)
    {
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            exit('Project not found');
        }

        $project = $this->config['projects'][$projectId];
        if (!\App\SPPDocs\Services\FeatureManager::isEnabled('views', $project)) {
            http_response_code(404);
            exit('Views feature is disabled for this project.');
        }
        $view = ViewStudioService::getView($projectId, $viewId);

        if (!$view) {
            http_response_code(404);
            exit('View not found');
        }

        $viewResult = ViewStudioService::executeView($projectId, $viewId, $_GET);

        // If format is json, return json immediately
        if (($view['display_format'] ?? '') === 'json') {
            Response::json($viewResult);
            return;
        }

        $tpl = \App\SPPDocs\Services\ThemeManager::resolveViewTemplate($projectId, $viewId);
        return $this->render($tpl, [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'view' => $view,
            'viewResult' => $viewResult,
            'base_url' => \SPP\App::getBaseUrl(),
        ]);
    }

    #[Route('/api/v1/views/{viewId}', method: 'GET')]
    public function apiShow($viewId)
    {
        $projectId = $_GET['project'] ?? 'spp';
        $result = ViewStudioService::executeView($projectId, $viewId, $_GET);
        Response::json($result);
    }
}