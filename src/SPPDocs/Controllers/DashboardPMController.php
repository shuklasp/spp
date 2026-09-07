<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\FeatureManager;
use SPPMod\SPPCache\SPPCacheManager;

class DashboardPMController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    #[Route('/pm/dashboard', method: 'GET')]
    public function index()
    {
        $projectId = $this->resolveActiveProjectId();
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('pm_dashboard', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        $allIssues = $this->loadAllIssues($issuesDir);
        $milestones = $this->loadMilestones($issuesDir);
        $teams = $this->loadTeams($issuesDir);

        // --- Stats ---
        $topLevel = array_filter($allIssues, fn($i) => empty($i['parent_id']));
        $openCount = count(array_filter($topLevel, fn($i) => ($i['status'] ?? 'open') === 'open'));
        $closedCount = count(array_filter($topLevel, fn($i) => ($i['status'] ?? 'open') === 'closed' || ($i['status'] ?? '') === 'done'));
        $overdueCount = 0;
        $today = time();
        foreach ($topLevel as $i) {
            if (($i['status'] ?? 'open') === 'open' && !empty($i['due_date'])) {
                $dueTs = is_numeric($i['due_date']) ? $i['due_date'] : strtotime($i['due_date']);
                if ($dueTs && $dueTs < $today) $overdueCount++;
            }
        }

        // --- Issues by Type ---
        $byType = ['task' => 0, 'bug' => 0, 'feature' => 0, 'epic' => 0];
        foreach ($topLevel as $i) {
            $t = $i['type'] ?? 'task';
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }

        // --- Workload per Assignee ---
        $workload = [];
        foreach ($allIssues as $i) {
            if (($i['status'] ?? 'open') !== 'open') continue;
            foreach ($i['assignees'] ?? [] as $a) {
                $workload[$a] = ($workload[$a] ?? 0) + 1;
            }
        }
        arsort($workload);

        // --- Velocity: issues closed per week (last 8 weeks) ---
        $velocity = [];
        for ($w = 7; $w >= 0; $w--) {
            $weekStart = strtotime("-{$w} week Monday");
            $weekEnd = $weekStart + 7 * 86400;
            $label = date('M j', $weekStart);
            $count = 0;
            foreach ($allIssues as $i) {
                if (($i['status'] ?? 'open') === 'closed' || ($i['status'] ?? '') === 'done') {
                    $closedAt = $i['updated_at'] ?? $i['created_at'] ?? 0;
                    if ($closedAt >= $weekStart && $closedAt < $weekEnd) $count++;
                }
            }
            $velocity[] = ['label' => $label, 'count' => $count];
        }

        // --- Recent Activity (from activity_log.jsonl) ---
        $recentActivity = [];
        $logFile = $issuesDir . '/activity_log.jsonl';
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice(array_reverse($lines), 0, 15);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) $recentActivity[] = $entry;
            }
        }

        // --- Total Time Logged ---
        $totalHours = 0;
        foreach ($allIssues as $i) {
            foreach ($i['time_logs'] ?? [] as $log) {
                $totalHours += (float) ($log['hours'] ?? 0);
            }
        }

        // --- Priority Distribution ---
        $byPriority = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($topLevel as $i) {
            if (($i['status'] ?? 'open') !== 'open') continue;
            $p = $i['priority'] ?? 'medium';
            $byPriority[$p] = ($byPriority[$p] ?? 0) + 1;
        }

        return $this->render('issues/dashboard', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'open_count' => $openCount, 'closed_count' => $closedCount,
            'overdue_count' => $overdueCount, 'total_count' => count($topLevel),
            'by_type' => $byType, 'by_priority' => $byPriority,
            'workload' => $workload, 'velocity' => $velocity,
            'recent_activity' => $recentActivity, 'total_hours' => $totalHours,
            'milestones' => $milestones,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    #[Route('/pm/roadmap', method: 'GET')]
    public function roadmap()
    {
        $projectId = $this->resolveActiveProjectId();
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('pm_dashboard', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        $allIssues = $this->loadAllIssues($issuesDir);
        $milestones = $this->loadMilestones($issuesDir);

        // Gather epics and milestones with dates for the roadmap
        $roadmapItems = [];

        foreach ($milestones as $msId => $ms) {
            if (empty($ms['start_date']) && empty($ms['end_date'])) continue;
            $linked = array_filter($allIssues, fn($i) => ($i['milestone_id'] ?? null) === $msId);
            $total = count($linked);
            $closed = count(array_filter($linked, fn($i) => ($i['status'] ?? 'open') === 'closed' || ($i['status'] ?? '') === 'done'));
            $roadmapItems[] = [
                'id' => $msId, 'type' => 'milestone', 'title' => $ms['title'],
                'start' => $ms['start_date'] ?? $ms['end_date'],
                'end' => $ms['end_date'] ?? $ms['start_date'],
                'progress' => $total > 0 ? round(($closed / $total) * 100) : 0,
                'color' => '#3b82f6',
            ];
        }

        foreach ($allIssues as $i) {
            if (($i['type'] ?? 'task') !== 'epic') continue;
            if (empty($i['due_date']) && empty($i['created_at'])) continue;
            $start = date('Y-m-d', $i['created_at'] ?? time());
            $end = !empty($i['due_date']) ? (is_numeric($i['due_date']) ? date('Y-m-d', $i['due_date']) : $i['due_date']) : $start;
            // Epic children progress
            $children = array_filter($allIssues, fn($c) => ($c['epic_id'] ?? null) === $i['id']);
            $total = count($children);
            $closed = count(array_filter($children, fn($c) => ($c['status'] ?? 'open') === 'closed'));
            $roadmapItems[] = [
                'id' => $i['id'], 'type' => 'epic', 'title' => $i['title'],
                'start' => $start, 'end' => $end,
                'progress' => $total > 0 ? round(($closed / $total) * 100) : 0,
                'color' => '#8b5cf6',
            ];
        }

        // Sort by start date
        usort($roadmapItems, fn($a, $b) => strcmp($a['start'], $b['start']));

        // Determine timeline range
        $allDates = array_merge(
            array_column($roadmapItems, 'start'),
            array_column($roadmapItems, 'end')
        );
        $minDate = !empty($allDates) ? min($allDates) : date('Y-m-d');
        $maxDate = !empty($allDates) ? max($allDates) : date('Y-m-d', strtotime('+3 months'));
        // Pad by 1 week on each side
        $timelineStart = date('Y-m-d', strtotime($minDate . ' -7 days'));
        $timelineEnd = date('Y-m-d', strtotime($maxDate . ' +7 days'));
        $totalDays = max(1, (strtotime($timelineEnd) - strtotime($timelineStart)) / 86400);

        // Generate month markers
        $monthMarkers = [];
        $d = strtotime(date('Y-m-01', strtotime($timelineStart)));
        while ($d <= strtotime($timelineEnd)) {
            $offset = (strtotime(date('Y-m-d', $d)) - strtotime($timelineStart)) / 86400;
            $pct = ($offset / $totalDays) * 100;
            $monthMarkers[] = ['label' => date('M Y', $d), 'pct' => max(0, $pct)];
            $d = strtotime('+1 month', $d);
        }

        return $this->render('issues/roadmap', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'items' => $roadmapItems, 'timeline_start' => $timelineStart,
            'timeline_end' => $timelineEnd, 'total_days' => $totalDays,
            'month_markers' => $monthMarkers,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    #[Route('/activity', method: 'GET')]
    public function activityFeed()
    {
        $projectId = $this->resolveActiveProjectId();
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('pm_dashboard', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        $activity = [];
        $logFile = $issuesDir . '/activity_log.jsonl';
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);
            // Paginate: 50 per page
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 50;
            $total = count($lines);
            $lines = array_slice($lines, ($page - 1) * $perPage, $perPage);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) $activity[] = $entry;
            }
        } else {
            $total = 0;
            $page = 1;
            $perPage = 50;
        }

        return $this->render('issues/activity', [
            'project' => $project, 'project_id' => $projectId,
            'activity' => $activity, 'page' => $page,
            'total_pages' => max(1, ceil($total / $perPage)),
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }
}
