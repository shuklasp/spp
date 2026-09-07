<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\DocumentCollabService;
use SPP\Response;

class CollabApiController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/api/collab/heartbeat', method: 'POST')]
    public function heartbeat()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $projectId = $input['project_id'] ?? '';
        $pageSlug = $input['page_slug'] ?? '';
        $status = $input['status'] ?? 'editing';
        $cursor = $input['cursor'] ?? null;

        $user = $this->getProjectUser($projectId) ?: ($input['username'] ?? 'Anonymous');

        if (empty($projectId) || empty($pageSlug)) {
            Response::json(['success' => false, 'error' => 'Missing project_id or page_slug'], 400);
            return;
        }

        $result = DocumentCollabService::heartbeat($projectId, $pageSlug, $user, $status, $cursor);
        Response::json($result);
    }

    #[Route('/api/collab/lock-section', method: 'POST')]
    public function lockSection()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $projectId = $input['project_id'] ?? '';
        $pageSlug = $input['page_slug'] ?? '';
        $sectionId = $input['section_id'] ?? '';
        $user = $this->getProjectUser($projectId) ?: ($input['username'] ?? 'Anonymous');

        if (empty($projectId) || empty($pageSlug) || empty($sectionId)) {
            Response::json(['success' => false, 'error' => 'Missing parameters'], 400);
            return;
        }

        $result = DocumentCollabService::acquireSectionLock($projectId, $pageSlug, $sectionId, $user);
        Response::json($result);
    }

    #[Route('/api/collab/unlock-section', method: 'POST')]
    public function unlockSection()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $projectId = $input['project_id'] ?? '';
        $pageSlug = $input['page_slug'] ?? '';
        $sectionId = $input['section_id'] ?? '';
        $user = $this->getProjectUser($projectId) ?: ($input['username'] ?? 'Anonymous');

        if (empty($projectId) || empty($pageSlug) || empty($sectionId)) {
            Response::json(['success' => false, 'error' => 'Missing parameters'], 400);
            return;
        }

        $result = DocumentCollabService::releaseSectionLock($projectId, $pageSlug, $sectionId, $user);
        Response::json($result);
    }

    #[Route('/api/collab/stream', method: 'GET')]
    public function stream()
    {
        $projectId = $_GET['project'] ?? $_GET['project_id'] ?? '';
        $pageSlug = $_GET['page'] ?? $_GET['page_slug'] ?? '';
        $currentUser = $this->getProjectUser($projectId) ?: 'Anonymous';

        if (empty($projectId) || empty($pageSlug)) {
            Response::json(['error' => 'Missing project or page'], 400);
            return;
        }

        // Return SSE stream or instant snapshot if SSE not supported
        if (headers_sent()) {
            exit;
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $state = DocumentCollabService::getState($projectId, $pageSlug, $currentUser);
        echo "event: presence\n";
        echo "data: " . json_encode($state) . "\n\n";
        if (ob_get_level() > 0) ob_flush();
        flush();
        exit;
    }

    #[Route('/api/collab/diff-merge', method: 'POST')]
    public function diffMerge()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $base = $input['base'] ?? '';
        $theirs = $input['theirs'] ?? '';
        $mine = $input['mine'] ?? '';

        $result = DocumentCollabService::diffMerge($base, $theirs, $mine);
        Response::json($result);
    }

    #[Route('/api/collab/leave', method: 'POST')]
    public function leave()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $projectId = $input['project_id'] ?? '';
        $pageSlug = $input['page_slug'] ?? '';
        $user = $this->getProjectUser($projectId) ?: ($input['username'] ?? 'Anonymous');

        if (!empty($projectId) && !empty($pageSlug)) {
            DocumentCollabService::leave($projectId, $pageSlug, $user);
        }

        Response::json(['success' => true]);
    }
}