<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;
use App\SPPDocs\Services\FeatureManager;
use SPP\App;
use SPP\Response;

class IssueController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    // =========================================================================
    //  ISSUE LIST
    // =========================================================================

    #[Route('/issues', method: 'GET')]
    public function index($projectId = null)
    {
        $projectId = $this->resolveActiveProjectId($projectId);
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }
        
        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('issues', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();
        
        $allIssues = $this->loadAllIssues($issuesDir);

        // Filtering
        $filterStatus = $_GET['status'] ?? 'open';
        $filterType = $_GET['type'] ?? '';
        $filterAssignee = $_GET['assignee'] ?? '';
        $filterLabel = $_GET['label'] ?? '';
        $filterMilestone = $_GET['milestone'] ?? '';
        $searchQ = trim($_GET['search'] ?? $_GET['query'] ?? '');
        if ($searchQ === '' && isset($_GET['q']) && $_GET['q'] !== 'issues' && $_GET['q'] !== 'sppdocs/issues') {
            $searchQ = trim($_GET['q']);
        }

        $filtered = array_filter($allIssues, function($issue) use ($filterStatus, $filterType, $filterAssignee, $filterLabel, $filterMilestone, $searchQ) {
            if ($filterStatus !== 'all' && ($issue['status'] ?? 'open') !== $filterStatus) return false;
            if ($filterType && ($issue['type'] ?? 'task') !== $filterType) return false;
            if ($filterAssignee && !in_array($filterAssignee, $issue['assignees'] ?? [])) return false;
            if ($filterLabel && !in_array($filterLabel, $issue['labels'] ?? [])) return false;
            if ($filterMilestone && ($issue['milestone_id'] ?? '') !== $filterMilestone) return false;
            if ($searchQ && stripos($issue['title'] ?? '', $searchQ) === false && stripos($issue['description'] ?? '', $searchQ) === false) return false;
            return true;
        });

        usort($filtered, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
        $tree = $this->buildIssueTree($filtered);

        // Server-Side Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));
        $totalItems = count($tree);
        $totalPages = max(1, (int)ceil($totalItems / $limit));
        $offset = ($page - 1) * $limit;
        $paginatedTree = array_slice($tree, $offset, $limit);
        $fromItem = $totalItems > 0 ? $offset + 1 : 0;
        $toItem = min($offset + $limit, $totalItems);

        $teams = $this->loadTeams($issuesDir);
        $milestones = $this->loadMilestones($issuesDir);
        $registeredUsers = $this->getRegisteredUsers();

        $allLabels = [];
        foreach ($allIssues as $issue) {
            foreach ($issue['labels'] ?? [] as $label) $allLabels[$label] = true;
        }

        $openCount = count(array_filter($allIssues, fn($i) => ($i['status'] ?? 'open') === 'open' && empty($i['parent_id'])));
        $closedCount = count(array_filter($allIssues, fn($i) => ($i['status'] ?? 'open') === 'closed' && empty($i['parent_id'])));

        return $this->render('issues/index', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'issues' => $paginatedTree, 'all_issues' => $allIssues,
            'page' => $page, 'limit' => $limit,
            'total_items' => $totalItems, 'total_pages' => $totalPages,
            'from_item' => $fromItem, 'to_item' => $toItem,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
            'teams' => $teams, 'milestones' => $milestones,
            'registered_users' => $registeredUsers, 'all_labels' => array_keys($allLabels),
            'filter_status' => $filterStatus, 'filter_type' => $filterType,
            'filter_assignee' => $filterAssignee, 'filter_label' => $filterLabel,
            'filter_milestone' => $filterMilestone, 'search_q' => $searchQ,
            'open_count' => $openCount, 'closed_count' => $closedCount,
        ]);
    }

    // =========================================================================
    //  KANBAN BOARD
    // =========================================================================

    #[Route('/issues/board', method: 'GET')]
    public function board()
    {
        $projectId = $this->resolveActiveProjectId();
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('issues', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        $allIssues = $this->loadAllIssues($issuesDir);
        // Filter to top-level only (no subtasks on the board)
        $boardIssues = array_filter($allIssues, fn($i) => empty($i['parent_id']));

        $columns = $project['board_columns'] ?? [
            ['id' => 'backlog', 'title' => 'Backlog', 'color' => '#94a3b8'],
            ['id' => 'todo', 'title' => 'To Do', 'color' => '#3b82f6'],
            ['id' => 'in_progress', 'title' => 'In Progress', 'color' => '#f59e0b'],
            ['id' => 'review', 'title' => 'Review', 'color' => '#8b5cf6'],
            ['id' => 'done', 'title' => 'Done', 'color' => '#22c55e'],
        ];

        // Map old status values to board columns
        $statusMap = ['open' => 'todo', 'closed' => 'done'];

        // Group issues by column
        $grouped = [];
        foreach ($columns as $col) $grouped[$col['id']] = [];
        foreach ($boardIssues as $issue) {
            $rawStatus = $issue['status'] ?? 'open';
            $colId = $statusMap[$rawStatus] ?? $rawStatus;
            if (!isset($grouped[$colId])) $colId = 'backlog';
            $grouped[$colId][] = $issue;
        }

        // Sort each column by sort_order (falling back to created_at desc)
        foreach ($grouped as $colId => &$colCards) {
            usort($colCards, function($a, $b) {
                $orderA = $a['sort_order'] ?? null;
                $orderB = $b['sort_order'] ?? null;
                if ($orderA !== null && $orderB !== null) {
                    return $orderA <=> $orderB;
                }
                if ($orderA !== null) return -1;
                if ($orderB !== null) return 1;
                return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
            });
        }
        unset($colCards);

        return $this->render('issues/board', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'columns' => $columns, 'grouped' => $grouped,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    #[Route('/issues/move', method: 'POST')]
    public function moveCard()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $issueId = $_POST['issue_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? '';
        $targetIndex = isset($_POST['target_index']) ? (int)$_POST['target_index'] : null;

        if (!$projectId || !$issueId || !$newStatus) {
            Response::json(['error' => 'Missing parameters'], 400);
        }

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) {
            Response::json(['error' => 'Project not found'], 404);
        }

        $issuesDir = $this->getIssuesDir($project);
        $issue = $this->loadIssue($issuesDir, $issueId, $project);
        if (!$issue) {
            Response::json(['error' => 'Issue not found'], 404);
        }

        $user = $this->getProjectUser();
        $oldStatus = $issue['status'] ?? 'open';
        $issue['status'] = $newStatus;
        $issue['updated_at'] = time();

        // Persistent Lexorank within-column card sequencing
        $allIssues = $this->loadAllIssues($issuesDir, $project);
        $colCards = array_values(array_filter($allIssues, function($i) use ($issueId, $newStatus) {
            return empty($i['parent_id']) && (($i['status'] ?? 'open') === $newStatus) && $i['id'] !== $issueId;
        }));

        usort($colCards, function($a, $b) {
            return ($a['sort_order'] ?? 999999) <=> ($b['sort_order'] ?? 999999);
        });

        $targetIndex = max(0, min(count($colCards), $targetIndex ?? count($colCards)));
        array_splice($colCards, $targetIndex, 0, [$issue]);

        foreach ($colCards as $idx => $card) {
            $cId = $card['id'] ?? null;
            if (!$cId) continue;
            if ($cId === $issueId) {
                $issue['sort_order'] = ($idx + 1) * 10;
            } else {
                $card['sort_order'] = ($idx + 1) * 10;
                unset($card['id']);
                $this->saveIssue($issuesDir, $cId, $card, $project);
            }
        }

        $issue['comments'][] = [
            'author' => 'System',
            'content' => ($user['username'] ?? 'Someone') . " moved this from '{$oldStatus}' to '{$newStatus}'.",
            'timestamp' => time(),
            'type' => 'event',
        ];
        unset($issue['id']);
        $this->saveIssue($issuesDir, $issueId, $issue, $project);
        $this->logActivity($issuesDir, 'issue.moved', $user['username'] ?? 'system', $issueId, "Moved to {$newStatus}");

        // Outbox Webhook Dispatch
        if (class_exists('\SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher')) {
            try {
                \SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher::queueWebhook('issue.moved', [
                    'issue_id' => $issueId,
                    'project_id' => $projectId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ], App::url('api/pm-issues'));
            } catch (\Throwable $e) {}
        }
        // Publish to Real-Time Event Stream
        \App\SPPDocs\Services\EventStreamService::publish($projectId, 'issue.moved', [
            'issue_id' => $issueId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'title' => $issue['title'] ?? '',
            'by' => $user['username'] ?? 'system',
        ]);

        Response::json(['ok' => true]);
    }

    #[Route('/issues/bulk-update', method: 'POST')]
    public function bulkUpdate()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $action = $_POST['bulk_action'] ?? '';
        $issueIds = $_POST['selected_issues'] ?? [];

        if (!$projectId || empty($issueIds) || !is_array($issueIds)) {
            Response::redirect(App::url('issues') . '?projectId=' . urlencode($projectId));
            return;
        }

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');

        $issuesDir = $this->getIssuesDir($project);
        $user = $this->getProjectUser();
        $actor = $user['username'] ?? 'admin';

        foreach ($issueIds as $id) {
            $issue = $this->loadIssue($issuesDir, $id, $project);
            if (!$issue) continue;

            if ($action === 'close') {
                $issue['status'] = 'closed';
                $issue['updated_at'] = time();
                $issue['comments'][] = ['author' => 'System', 'content' => "$actor bulk closed this issue.", 'timestamp' => time(), 'type' => 'event'];
                unset($issue['id']);
                $this->saveIssue($issuesDir, $id, $issue, $project);
            } elseif ($action === 'reopen') {
                $issue['status'] = 'open';
                $issue['updated_at'] = time();
                $issue['comments'][] = ['author' => 'System', 'content' => "$actor bulk reopened this issue.", 'timestamp' => time(), 'type' => 'event'];
                unset($issue['id']);
                $this->saveIssue($issuesDir, $id, $issue, $project);
            } elseif ($action === 'delete') {
                $storage = \App\SPPDocs\Storage\StorageFactory::create($project, $issuesDir);
                $storage->delete($id);
            }
        }

        $this->logActivity($issuesDir, 'issue.bulk_' . $action, $actor, 'bulk', "Bulk updated " . count($issueIds) . " issues ({$action})");
        Response::redirect(App::url('issues') . '?projectId=' . urlencode($projectId));
    }

    // =========================================================================
    //  VIEW ISSUE
    // =========================================================================

    #[Route('/issues/view', method: 'GET')]
    public function viewIssue()
    {
        $projectId = $this->resolveActiveProjectId();
        $issueId = $_GET['issueId'] ?? null;
        
        if (!$projectId || !$issueId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Issue or project not found']);
        }
        
        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('issues', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();
        
        $issue = $this->loadIssue($issuesDir, $issueId);
        if (!$issue) return $this->render('errors/404', ['message' => 'Issue not found']);

        $allIssues = $this->loadAllIssues($issuesDir);
        $subtasks = array_values(array_filter($allIssues, fn($i) => ($i['parent_id'] ?? null) === $issueId));
        usort($subtasks, fn($a, $b) => ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0));

        $epicChildren = [];
        if (($issue['type'] ?? 'task') === 'epic') {
            $epicChildren = array_values(array_filter($allIssues, fn($i) => ($i['epic_id'] ?? null) === $issueId));
            usort($epicChildren, fn($a, $b) => ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0));
        }

        $parentIssue = null;
        if (!empty($issue['parent_id'])) {
            $parentIssue = $this->loadIssue($issuesDir, $issue['parent_id']);
        }

        $teams = $this->loadTeams($issuesDir);
        $milestones = $this->loadMilestones($issuesDir);
        $registeredUsers = $this->getRegisteredUsers();
        $epics = array_values(array_filter($allIssues, fn($i) => ($i['type'] ?? 'task') === 'epic' && $i['id'] !== $issueId));

        // Compute total logged time
        $totalHours = 0;
        foreach ($issue['time_logs'] ?? [] as $log) {
            $totalHours += (float) ($log['hours'] ?? 0);
        }

        return $this->render('issues/view', [
            'project' => $project, 'project_id' => $projectId,
            'issue' => $issue, 'subtasks' => $subtasks,
            'epic_children' => $epicChildren, 'parent_issue' => $parentIssue,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
            'teams' => $teams, 'milestones' => $milestones,
            'registered_users' => $registeredUsers, 'epics' => $epics,
            'total_hours' => $totalHours,
        ]);
    }

    // =========================================================================
    //  CREATE ISSUE
    // =========================================================================

    #[Route('/issues/create', method: 'POST')]
    public function create()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $projectId = $this->resolveActiveProjectId();
            Response::redirect(App::url('issues') . "?projectId={$projectId}&new=1");
            return;
        }

        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $title = strip_tags($_POST['title'] ?? '');
        $description = strip_tags($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'task';
        $parentId = $_POST['parent_id'] ?? null;
        $epicId = $_POST['epic_id'] ?? null;
        $milestoneId = $_POST['milestone_id'] ?? null;
        $labels = array_filter(array_map('trim', explode(',', $_POST['labels'] ?? '')));
        $assignees = $_POST['assignees'] ?? [];
        if (is_string($assignees)) $assignees = array_filter([$assignees]);
        $teamId = $_POST['team_id'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        $dueDate = $_POST['due_date'] ?? null;
        
        if (!$projectId || !trim($title)) {
            exit('Invalid input: Title is required');
        }
        
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        FeatureManager::requireFeature('issues', $project, $projectId);
        
        $issuesDir = $this->getIssuesDir($project);
        $user = $this->getProjectUser();
        $allowGuest = $project['forums_guest_posting'] ?? false;
        if (!$user && !$allowGuest) exit('Access denied. Please login to create an issue.');

        $validTypes = ['epic', 'task', 'bug', 'feature', 'subtask'];
        if (!in_array($type, $validTypes)) $type = 'task';
        if ($parentId) $type = 'subtask';
        
        $issueId = 'issue_' . time() . '_' . bin2hex(random_bytes(4));
        $issueData = [
            'title' => $title,
            'description' => $description,
            'author' => $user ? $user['username'] : 'Guest',
            'type' => $type,
            'status' => 'open',
            'priority' => $priority,
            'labels' => $labels,
            'assignees' => $assignees,
            'team_id' => $teamId ?: null,
            'parent_id' => $parentId ?: null,
            'epic_id' => $epicId ?: null,
            'milestone_id' => $milestoneId ?: null,
            'due_date' => $dueDate ?: null,
            'created_at' => time(),
            'updated_at' => time(),
            'comments' => [],
            'time_logs' => [],
        ];
        
        $this->saveIssue($issuesDir, $issueId, $issueData);
        $this->logActivity($issuesDir, 'issue.created', $user['username'] ?? 'Guest', $issueId, "Created '{$title}'");

        // Transactional Outbox Webhook Dispatch
        if (class_exists('\SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher')) {
            try {
                \SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher::queueWebhook('issue.created', $issueData, App::url('api/pm-issues'));
            } catch (\Throwable $e) {}
        }
        // Publish to Real-Time Event Stream
        \App\SPPDocs\Services\EventStreamService::publish($projectId, 'issue.created', [
            'issue_id' => $issueId,
            'title' => $title,
            'status' => $issueData['status'] ?? 'open',
            'type' => $issueData['type'] ?? 'task',
            'priority' => $issueData['priority'] ?? 'medium',
            'by' => $user['username'] ?? 'Guest',
        ]);

        $targetId = $parentId ?: $issueId;
        Response::redirect(App::url('issues/view') . "?projectId={$projectId}&issueId={$targetId}");
    }

    // =========================================================================
    //  COMMENT
    // =========================================================================

    #[Route('/issues/comment', method: 'POST')]
    public function postComment()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $issueId = $_POST['issue_id'] ?? '';
        $content = strip_tags($_POST['content'] ?? '');
        
        if (!$projectId || !$issueId || !trim($content)) exit('Invalid input');
        
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        
        $issuesDir = $this->getIssuesDir($project);
        $user = $this->getProjectUser();
        $allowGuest = $project['forums_guest_posting'] ?? false;
        if (!$user && !$allowGuest) exit('Access denied.');
        
        $issue = $this->loadIssue($issuesDir, $issueId);
        if (!$issue) exit('Issue not found');
        
        $issue['comments'][] = [
            'author' => $user ? $user['username'] : 'Guest',
            'content' => $content,
            'timestamp' => time(),
        ];
        $issue['updated_at'] = time();
        unset($issue['id']);
        $this->saveIssue($issuesDir, $issueId, $issue);
        $this->logActivity($issuesDir, 'issue.commented', $user['username'] ?? 'Guest', $issueId, "Commented on '{$issue['title']}'");

        // Notify assignees about the comment
        $commenter = $user ? $user['username'] : 'Guest';
        foreach ($issue['assignees'] ?? [] as $assignee) {
            if ($assignee !== $commenter) {
                NotificationController::notify($issuesDir, $assignee, 'commented', "{$commenter} commented on \"{$issue['title']}\"", App::url('issues/view') . "?projectId={$projectId}&issueId={$issueId}");
            }
        }
        
        Response::redirect(App::url('issues/view') . "?projectId={$projectId}&issueId={$issueId}");
    }

    // =========================================================================
    //  UPDATE (status, assign, priority, labels, epic, merge, milestone, due_date)
    // =========================================================================

    #[Route('/issues/update', method: 'POST')]
    public function update()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $issueId = $_POST['issue_id'] ?? '';
        $action = $_POST['action'] ?? '';
        
        if (!$projectId || !$issueId) exit('Invalid input');
        
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        FeatureManager::requireFeature('issues', $project, $projectId);

        $user = $this->getProjectUser();
        if (!$user) exit('Access denied');
        
        $issuesDir = $this->getIssuesDir($project);
        $issue = $this->loadIssue($issuesDir, $issueId);
        if (!$issue) exit('Issue not found');

        switch ($action) {
            case 'toggle_status':
                $oldStatus = $issue['status'] ?? 'open';
                $newStatus = ($oldStatus === 'open') ? 'closed' : 'open';
                $issue['status'] = $newStatus;
                $issue['comments'][] = [
                    'author' => 'System', 'type' => 'event', 'timestamp' => time(),
                    'content' => $user['username'] . ' ' . ($newStatus === 'closed' ? 'closed' : 'reopened') . ' this issue.',
                ];
                $this->logActivity($issuesDir, 'issue.' . $newStatus, $user['username'], $issueId, ucfirst($newStatus) . " '{$issue['title']}'");

                // Outbox Webhook Dispatch
                if (class_exists('\SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher')) {
                    try {
                        \SPPMod\SPPWorkflow\CQRS\OutboxWebhookDispatcher::queueWebhook('issue.' . $newStatus, [
                            'issue_id' => $issueId,
                            'project_id' => $projectId,
                            'status' => $newStatus,
                            'actor' => $user['username'],
                        ], App::url('api/pm-issues'));
                    } catch (\Throwable $e) {}
                }
                break;

            case 'assign':
                $assignees = $_POST['assignees'] ?? [];
                if (is_string($assignees)) $assignees = array_filter([$assignees]);
                $issue['assignees'] = $assignees;
                $issue['comments'][] = [
                    'author' => 'System', 'type' => 'event', 'timestamp' => time(),
                    'content' => $user['username'] . ' assigned this to ' . (empty($assignees) ? 'nobody' : implode(', ', $assignees)) . '.',
                ];
                // Notify assigned users
                foreach ($assignees as $assignee) {
                    if ($assignee !== $user['username']) {
                        NotificationController::notify($issuesDir, $assignee, 'assigned', $user['username'] . " assigned you to \"{$issue['title']}\"", App::url('issues/view') . "?projectId={$projectId}&issueId={$issueId}");
                    }
                }
                break;

            case 'assign_team':
                $teamId = $_POST['team_id'] ?? null;
                $issue['team_id'] = $teamId ?: null;
                $teams = $this->loadTeams($issuesDir);
                $teamName = $teams[$teamId]['name'] ?? $teamId;
                $issue['comments'][] = [
                    'author' => 'System', 'type' => 'event', 'timestamp' => time(),
                    'content' => $user['username'] . ($teamId ? " assigned this to team '{$teamName}'." : ' removed team assignment.'),
                ];
                break;

            case 'set_priority':
                $priority = $_POST['priority'] ?? 'medium';
                if (in_array($priority, ['low', 'medium', 'high', 'critical'])) {
                    $issue['priority'] = $priority;
                    $issue['comments'][] = [
                        'author' => 'System', 'type' => 'event', 'timestamp' => time(),
                        'content' => $user['username'] . " set priority to {$priority}.",
                    ];
                }
                break;

            case 'set_type':
                $type = $_POST['type'] ?? 'task';
                if (in_array($type, ['epic', 'task', 'bug', 'feature', 'subtask'])) $issue['type'] = $type;
                break;

            case 'set_labels':
                $issue['labels'] = array_filter(array_map('trim', explode(',', $_POST['labels'] ?? '')));
                break;

            case 'link_epic':
                $issue['epic_id'] = ($_POST['epic_id'] ?? '') ?: null;
                break;

            case 'set_milestone':
                $issue['milestone_id'] = ($_POST['milestone_id'] ?? '') ?: null;
                break;

            case 'set_due_date':
                $issue['due_date'] = ($_POST['due_date'] ?? '') ?: null;
                break;

            case 'merge':
                $sourceId = $_POST['source_issue_id'] ?? '';
                if ($sourceId) {
                    $src = $this->loadIssue($issuesDir, $sourceId);
                    if ($src) {
                        foreach ($src['comments'] ?? [] as $c) $issue['comments'][] = $c;
                        $src['status'] = 'closed';
                        $src['merged_into'] = $issueId;
                        $src['comments'][] = ['author' => 'System', 'type' => 'event', 'timestamp' => time(), 'content' => "Merged into #{$issueId} by {$user['username']}."];
                        unset($src['id']);
                        $this->saveIssue($issuesDir, $sourceId, $src);
                        $issue['comments'][] = ['author' => 'System', 'type' => 'event', 'timestamp' => time(), 'content' => "{$user['username']} merged #{$sourceId} (\"{$src['title']}\") into this issue."];
                    }
                }
                break;
        }

        $issue['updated_at'] = time();
        unset($issue['id']);
        $this->saveIssue($issuesDir, $issueId, $issue);
        
        Response::redirect(App::url('issues/view') . "?projectId={$projectId}&issueId={$issueId}");
    }

    // =========================================================================
    //  TIME TRACKING
    // =========================================================================

    #[Route('/issues/log-time', method: 'POST')]
    public function logTime()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $issueId = $_POST['issue_id'] ?? '';
        $hours = (float) ($_POST['hours'] ?? 0);
        $description = strip_tags($_POST['description'] ?? '');

        if (!$projectId || !$issueId || $hours <= 0) exit('Invalid input');

        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $user = $this->getProjectUser();
        if (!$user) exit('Access denied');

        $issuesDir = $this->getIssuesDir($project);
        $issue = $this->loadIssue($issuesDir, $issueId);
        if (!$issue) exit('Issue not found');

        if (!isset($issue['time_logs'])) $issue['time_logs'] = [];
        $issue['time_logs'][] = [
            'user' => $user['username'],
            'hours' => $hours,
            'description' => $description,
            'logged_at' => time(),
        ];

        $issue['comments'][] = [
            'author' => 'System', 'type' => 'event', 'timestamp' => time(),
            'content' => $user['username'] . " logged {$hours}h" . ($description ? ": {$description}" : '.'),
        ];
        $issue['updated_at'] = time();
        unset($issue['id']);
        $this->saveIssue($issuesDir, $issueId, $issue);
        $this->logActivity($issuesDir, 'issue.time_logged', $user['username'], $issueId, "Logged {$hours}h on '{$issue['title']}'");

        Response::redirect(App::url('issues/view') . "?projectId={$projectId}&issueId={$issueId}");
    }

    // =========================================================================
    //  CALENDAR VIEW
    // =========================================================================

    #[Route('/issues/calendar', method: 'GET')]
    public function calendar()
    {
        $projectId = $this->resolveActiveProjectId();
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }

        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('issues', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        $allIssues = $this->loadAllIssues($issuesDir);
        $datedIssues = array_filter($allIssues, fn($i) => !empty($i['due_date']));

        // Get month/year from query or default to current
        $month = (int) ($_GET['month'] ?? date('n'));
        $year = (int) ($_GET['year'] ?? date('Y'));

        // Build calendar grid
        $firstDay = mktime(0, 0, 0, $month, 1, $year);
        $daysInMonth = (int) date('t', $firstDay);
        $startWeekday = (int) date('w', $firstDay); // 0=Sun

        // Group issues by day
        $issuesByDay = [];
        foreach ($datedIssues as $issue) {
            $dueTs = is_numeric($issue['due_date']) ? $issue['due_date'] : strtotime($issue['due_date']);
            if ($dueTs && (int) date('n', $dueTs) === $month && (int) date('Y', $dueTs) === $year) {
                $day = (int) date('j', $dueTs);
                $issuesByDay[$day][] = $issue;
            }
        }

        return $this->render('issues/calendar', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'month' => $month, 'year' => $year,
            'days_in_month' => $daysInMonth, 'start_weekday' => $startWeekday,
            'issues_by_day' => $issuesByDay,
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    // =========================================================================
    //  TEAMS
    // =========================================================================

    #[Route('/issues/teams', method: 'GET')]
    public function teamsIndex()
    {
        $projectId = $this->resolveActiveProjectId();
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            return $this->render('errors/404', ['message' => 'Project not found. Please select a valid project from the portal.']);
        }
        $project = $this->config['projects'][$projectId];
        FeatureManager::requireFeature('issues', $project, $projectId);

        $issuesDir = $this->getIssuesDir($project);
        $this->ensureSession();

        return $this->render('issues/teams', [
            'project' => $project, 'project_id' => $projectId,
            'all_projects' => $this->config['projects'] ?? [],
            'teams' => $this->loadTeams($issuesDir),
            'registered_users' => $this->getRegisteredUsers(),
            'user' => $this->getProjectUser(), 'csrf_token' => $_SESSION['sppdocs_csrf'],
        ]);
    }

    #[Route('/issues/teams/save', method: 'POST')]
    public function saveTeam()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $teamName = strip_tags(trim($_POST['team_name'] ?? ''));
        $members = $_POST['members'] ?? [];
        if (is_string($members)) $members = array_filter(array_map('trim', explode(',', $members)));
        if (!$projectId || !$teamName) exit('Invalid input');
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $user = $this->getProjectUser();
        if (!$user || $user['role'] !== 'admin') exit('Only admins can manage teams.');
        $issuesDir = $this->getIssuesDir($project);
        $teams = $this->loadTeams($issuesDir);
        $teamId = $_POST['team_id'] ?? ('team_' . bin2hex(random_bytes(4)));
        $teams[$teamId] = ['name' => $teamName, 'members' => $members, 'created_at' => $teams[$teamId]['created_at'] ?? time()];
        $this->saveTeams($issuesDir, $teams);
        Response::redirect(App::url('issues/teams') . "?projectId={$projectId}");
    }

    #[Route('/issues/teams/delete', method: 'POST')]
    public function deleteTeam()
    {
        $this->verifyCsrf();
        $projectId = $_POST['project_id'] ?? '';
        $teamId = $_POST['team_id'] ?? '';
        if (!$projectId || !$teamId) exit('Invalid input');
        $project = $this->config['projects'][$projectId] ?? null;
        if (!$project) exit('Project not found');
        $user = $this->getProjectUser();
        if (!$user || $user['role'] !== 'admin') exit('Only admins can manage teams.');
        $issuesDir = $this->getIssuesDir($project);
        $teams = $this->loadTeams($issuesDir);
        unset($teams[$teamId]);
        $this->saveTeams($issuesDir, $teams);
        Response::redirect(App::url('issues/teams') . "?projectId={$projectId}");
    }
}
