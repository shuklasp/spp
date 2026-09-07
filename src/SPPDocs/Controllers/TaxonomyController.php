<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\TaxonomyService;

class TaxonomyController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/admin/taxonomy', method: 'GET')]
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
        \App\SPPDocs\Services\FeatureManager::requireFeature('taxonomy', $project, $projectId);

        $vocabs = TaxonomyService::listVocabularies($projectId);
        $activeVocabKey = $_GET['vocab'] ?? (array_key_first($vocabs) ?: 'categories');
        $activeVocab = $vocabs[$activeVocabKey] ?? null;
        $hierarchyTree = $activeVocabKey ? TaxonomyService::getHierarchyTree($projectId, $activeVocabKey) : [];

        return $this->render('admin.taxonomy', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'vocabularies' => $vocabs,
            'activeVocabKey' => $activeVocabKey,
            'activeVocab' => $activeVocab,
            'hierarchyTree' => $hierarchyTree,
            'active_tab' => 'taxonomy',
            'csrf_token' => $_SESSION['sppdocs_csrf'] ?? '',
            'user' => $this->getProjectUser(),
        ]);
    }

    #[Route('/admin/taxonomy/save', method: 'POST')]
    public function saveTerm()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('taxonomy', $project, $projectId);

        $vocab = trim($_POST['vocab'] ?? 'categories');
        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $parent = trim($_POST['parent'] ?? '') ?: null;
        $color = trim($_POST['color'] ?? '#3b82f6');
        $icon = trim($_POST['icon'] ?? '🏷️');
        $description = trim($_POST['description'] ?? '');

        if (!empty($slug) && !empty($name)) {
            TaxonomyService::saveTerm($projectId, $vocab, $slug, [
                'name' => $name,
                'parent' => $parent,
                'color' => $color,
                'icon' => $icon,
                'description' => $description,
            ]);
            $_SESSION['adm_flash_success'] = "Taxonomy term '{$name}' saved successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/taxonomy?project=" . urlencode($projectId) . "&vocab=" . urlencode($vocab));
        exit;
    }

    #[Route('/admin/taxonomy/delete', method: 'POST')]
    public function deleteTerm()
    {
        $this->requireAuth();
        $projectId = $_POST['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }
        $this->requireProjectAdmin($projectId);
        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('taxonomy', $project, $projectId);

        $vocab = trim($_POST['vocab'] ?? 'categories');
        $slug = trim($_POST['slug'] ?? '');

        if (!empty($slug)) {
            TaxonomyService::deleteTerm($projectId, $vocab, $slug);
            $_SESSION['adm_flash_success'] = "Term '{$slug}' deleted successfully.";
        }

        header("Location: " . \SPP\App::getBaseUrl() . "/admin/taxonomy?project=" . urlencode($projectId) . "&vocab=" . urlencode($vocab));
        exit;
    }

    #[Route('/project/{projectId}/taxonomy/{vocab}/{slug}', method: 'GET')]
    public function archive($projectId, $vocab, $slug)
    {
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            http_response_code(404);
            exit('Project not found');
        }

        $project = $this->config['projects'][$projectId];
        if (!\App\SPPDocs\Services\FeatureManager::isEnabled('taxonomy', $project)) {
            http_response_code(404);
            exit('Taxonomy feature is disabled for this project.');
        }
        $vocabulary = TaxonomyService::getVocabulary($projectId, $vocab);
        $term = $vocabulary['terms'][$slug] ?? null;

        if (!$term) {
            http_response_code(404);
            exit('Taxonomy term not found');
        }

        $documents = TaxonomyService::getDocumentsForTerm($projectId, $vocab, $slug);

        $tpl = \App\SPPDocs\Services\ThemeManager::resolveTaxonomyTemplate($projectId, $vocab);
        return $this->render($tpl, [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'vocabulary' => $vocabulary,
            'term' => $term,
            'documents' => $documents,
            'base_url' => \SPP\App::getBaseUrl(),
        ]);
    }
}