<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\ThemeManager;
use App\SPPDocs\Services\BlockManager;
use App\SPPDocs\Services\ViewStudioService;
use App\SPPDocs\Services\TaxonomyService;
use App\SPPDocs\Services\PermissionManager;
use SPP\Response;

class BlockController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/admin/blocks', method: 'GET')]
    public function adminIndex()
    {
        $this->requireAuth();
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? 'spp';
        $this->loadProjectConfig();

        if (!isset($this->config['projects'][$projectId])) {
            $keys = array_keys($this->config['projects'] ?? []);
            $projectId = $keys[0] ?? 'spp';
        }

        $project = $this->config['projects'][$projectId] ?? ['title' => ucfirst($projectId)];
        $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';

        if (!PermissionManager::isProjectAdmin($projectId, $currentUser)) {
            http_response_code(403);
            return $this->render('errors/403', ['message' => 'Unauthorized: Admin access required to manage regions and blocks.']);
        }
        \App\SPPDocs\Services\FeatureManager::requireFeature('blocks', $project, $projectId);

        $theme = ThemeManager::getTheme($projectId);
        $regions = ThemeManager::getThemeRegions($projectId);
        $allBlocks = BlockManager::getAllBlocks($projectId);

        // Fetch available views and taxonomies for placement
        $views = ViewStudioService::getViews($projectId);
        $vocabularies = TaxonomyService::listVocabularies($projectId);

        // Fetch user roles for visibility selector
        $roles = PermissionManager::getRoles($projectId);

        return $this->render('admin.blocks', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'theme' => $theme,
            'regions' => $regions,
            'allBlocks' => $allBlocks,
            'views' => $views,
            'vocabularies' => $vocabularies,
            'roles' => $roles,
            'active_tab' => 'blocks',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $currentUser,
        ]);
    }

    #[Route('/admin/blocks/save', method: 'POST')]
    public function saveBlock()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? 'spp';
        $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';

        if (!PermissionManager::isProjectAdmin($projectId, $currentUser)) {
            Response::json(['error' => 'Unauthorized'], 403);
            return;
        }

        $region = trim($_POST['region'] ?? '');
        if (empty($region)) {
            Response::json(['error' => 'Region is required'], 400);
            return;
        }

        $id = trim($_POST['id'] ?? '');
        if (empty($id)) {
            $id = 'block_' . time() . '_' . substr(md5(uniqid()), 0, 6);
        }

        $type = trim($_POST['type'] ?? 'alert');
        $title = trim($_POST['title'] ?? ucfirst(str_replace('_', ' ', $type)));
        $weight = (int)($_POST['weight'] ?? 0);

        // Visibility parsing
        $pathsRaw = trim($_POST['paths'] ?? '*');
        $paths = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $pathsRaw)));
        if (empty($paths)) {
            $paths = ['*'];
        }

        $rolesRaw = $_POST['roles'] ?? ['*'];
        $roles = is_array($rolesRaw) ? $rolesRaw : array_filter(array_map('trim', explode(',', (string)$rolesRaw)));
        if (empty($roles)) {
            $roles = ['*'];
        }

        $blockData = [
            'id' => $id,
            'title' => $title,
            'type' => $type,
            'weight' => $weight,
            'visibility' => [
                'paths' => $paths,
                'roles' => $roles,
            ],
        ];

        // Specific fields per type
        if ($type === 'view') {
            $blockData['view_id'] = trim($_POST['view_id'] ?? '');
        } elseif ($type === 'taxonomy_tree') {
            $blockData['vocab'] = trim($_POST['vocab'] ?? 'categories');
        } elseif ($type === 'alert') {
            $blockData['alert_type'] = trim($_POST['alert_type'] ?? 'info');
            $blockData['content'] = trim($_POST['content'] ?? '');
        } elseif ($type === 'custom') {
            $blockData['plugin_id'] = trim($_POST['plugin_id'] ?? '');
            $blockData['content'] = trim($_POST['content'] ?? '');
        }

        BlockManager::saveBlock($projectId, $region, $blockData);

        if (!empty($_SERVER['HTTP_HX_REQUEST']) || !empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            Response::json(['success' => true, 'block' => $blockData]);
            return;
        }

        header('Location: ' . \SPP\App::getBaseUrl() . '/admin/blocks?project=' . urlencode($projectId));
        exit;
    }

    #[Route('/admin/blocks/delete', method: 'POST')]
    public function deleteBlock()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? 'spp';
        $blockId = $_POST['id'] ?? $_POST['block_id'] ?? '';
        $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';

        if (!PermissionManager::isProjectAdmin($projectId, $currentUser)) {
            Response::json(['error' => 'Unauthorized'], 403);
            return;
        }

        if (empty($blockId)) {
            Response::json(['error' => 'Block ID is required'], 400);
            return;
        }

        $deleted = BlockManager::deleteBlock($projectId, $blockId);

        if (!empty($_SERVER['HTTP_HX_REQUEST']) || !empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            Response::json(['success' => $deleted]);
            return;
        }

        header('Location: ' . \SPP\App::getBaseUrl() . '/admin/blocks?project=' . urlencode($projectId));
        exit;
    }

    #[Route('/admin/blocks/reorder', method: 'POST')]
    public function reorderBlocks()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? 'spp';
        $region = $_POST['region'] ?? '';
        $order = $_POST['order'] ?? [];
        $currentUser = $_SESSION['sppdocs_user'] ?? 'admin';

        if (!PermissionManager::isProjectAdmin($projectId, $currentUser)) {
            Response::json(['error' => 'Unauthorized'], 403);
            return;
        }

        if (empty($region) || !is_array($order)) {
            Response::json(['error' => 'Invalid parameters'], 400);
            return;
        }

        BlockManager::reorderBlocks($projectId, $region, $order);
        Response::json(['success' => true]);
    }
}
