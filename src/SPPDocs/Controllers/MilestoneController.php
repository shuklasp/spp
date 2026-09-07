<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\FeatureManager;
use SPP\App;
use SPP\Response;

class MilestoneController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/milestones', method: 'GET')]
    public function index()
    {
        $projectId = $this->resolveActiveProjectId();
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('milestones', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        $milestones = $this->loadMilestones($issuesDir);
        $allIssues = $this->loadAllIssues($issuesDir);

        // Compute stats for each milestone
        $milestoneStats = [];
        foreach ($milestones as $msId => $ms) {
            $linked = array_filter($allIssues, fn($i) => ($i['milestone_id'] ?? null) === $msId);
            $total = count($linked);
            $closed = count(array_filter($linked, fn($i) => ($i['status'] ?? 'open') === 'closed' || ($i['status'] ?? 'open') === 'done'));
            $pct = $total > 0 ? round(($closed / $total) * 100) : 0;

            // Auto-compute status
            $today = date('Y-m-d');
            $status = $ms['status'] ?? 'active';
            if (!empty($ms['end_date']) && $ms['end_date'] < $today && $pct < 100) $status = 'overdue';
            elseif ($total > 0 && $pct === 100) $status = 'completed';
            elseif (!empty($ms['start_date']) && $ms['start_date'] > $today) $status = 'upcoming';
            else $status = 'active';

            $milestoneStats[$msId] = [
                'total' => $total, 'closed' => $closed, 'pct' => $pct, 'status' => $status,
            ];
        }

        return $this->render('issues/milestones', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'milestones' => $milestones, 'stats' => $milestoneStats,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    #[Route('/milestones/view', method: 'GET')]
    public function view()
    {
        $projectId = $this->resolveActiveProjectId();
        $msId = $_GET['milestoneId'] ?? null;

        if (!$projectId || !$msId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Milestone not found']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('milestones', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        $milestones = $this->loadMilestones($issuesDir);
        if (!isset($milestones[$msId])) {
            return $this->render('errors/404', ['message' => 'Milestone not found']);
        }

        $milestone = $milestones[$msId];
        $milestone['id'] = $msId;

        $allIssues = $this->loadAllIssues($issuesDir);
        $linked = array_values(array_filter($allIssues, fn($i) => ($i['milestone_id'] ?? null) === $msId));
        usort($linked, fn($a, $b) => ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0));

        $total = count($linked);
        $closed = count(array_filter($linked, fn($i) => ($i['status'] ?? 'open') === 'closed' || ($i['status'] ?? 'open') === 'done'));
        $pct = $total > 0 ? round(($closed / $total) * 100) : 0;

        // Total hours logged in this milestone
        $totalHours = 0;
        foreach ($linked as $issue) {
            foreach ($issue['time_logs'] ?? [] as $log) {
                $totalHours += (float) ($log['hours'] ?? 0);
            }
        }

        return $this->render('issues/milestone_view', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'milestone' => $milestone, 'issues' => $linked,
            'total' => $total, 'closed' => $closed, 'pct' => $pct,
            'total_hours' => $totalHours,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    #[Route('/milestones/save', method: 'POST')]
    public function save()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $title = strip_tags(trim($_POST['title'] ?? ''));
        $description = strip_tags($_POST['description'] ?? '');
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;

        if (!$projectId || !$title) exit('Invalid input: Title is required');

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        FeatureManager::requireFeature('milestones', $project, $projectId);

        $user = $this->getProjectUser();
        if (!$user) exit('Access denied');

        $issuesDir = $this->getIssuesDir($project);
        $milestones = $this->loadMilestones($issuesDir);

        $msId = $_POST['milestone_id'] ?? ('ms_' . time() . '_' . bin2hex(random_bytes(4)));
        $milestones[$msId] = [
            'title' => $title,
            'description' => $description,
            'start_date' => $startDate ?: null,
            'end_date' => $endDate ?: null,
            'status' => 'active',
            'created_by' => $user['username'],
            'created_at' => $milestones[$msId]['created_at'] ?? time(),
        ];

        $this->saveMilestones($issuesDir, $milestones);
        $this->logActivity($issuesDir, 'milestone.saved', $user['username'], $msId, "Saved milestone '{$title}'");

        Response::redirect(App::url('milestones') . "?projectId={$projectId}");
    }

    #[Route('/milestones/delete', method: 'POST')]
    public function delete()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $msId = $_POST['milestone_id'] ?? '';

        if (!$projectId || !$msId) exit('Invalid input');

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        FeatureManager::requireFeature('milestones', $project, $projectId);

        $user = $this->getProjectUser();
        if (!$user || $user['role'] !== 'admin') exit('Only admins can delete milestones.');

        $issuesDir = $this->getIssuesDir($project);
        $milestones = $this->loadMilestones($issuesDir);
        unset($milestones[$msId]);
        $this->saveMilestones($issuesDir, $milestones);

        Response::redirect(App::url('milestones') . "?projectId={$projectId}");
    }
}
