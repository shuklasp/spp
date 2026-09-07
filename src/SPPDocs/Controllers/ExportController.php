<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\BookExportService;

class ExportController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/export/book', method: 'GET')]
    public function book()
    {
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $keys = array_keys($this->config['projects'] ?? []);
            $projectId = $keys[0] ?? '';
        }

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('book_export', $project, $projectId);
        $version = $_GET['version'] ?? ($project['default_version'] ?? 'v1');

        $book = BookExportService::compileBook($project, $projectId, $version);

        return $this->render('export.book', [
            'projectId' => $projectId,
            'project_id' => $projectId,
            'project' => $project,
            'version' => $version,
            'book' => $book,
            'auto_print' => !empty($_GET['print']),
        ]);
    }

    #[Route('/export/markdown', method: 'GET')]
    public function markdown()
    {
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $this->loadProjectConfig();

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            exit('Project not found');
        }

        $project = $this->config['projects'][$projectId];
        \App\SPPDocs\Services\FeatureManager::requireFeature('book_export', $project, $projectId);
        $version = $_GET['version'] ?? ($project['default_version'] ?? 'v1');

        $md = BookExportService::compileMarkdown($project, $projectId, $version);

        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $projectId . '-docs-' . $version . '.md"');
        echo $md;
        exit;
    }
}