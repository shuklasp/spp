<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Traits\ProjectAwareTrait;

use SPP\Response;
use App\SPPDocs\Services\FeatureManager;

class IssueApiController extends \SPPMod\SPPView\ViewController
{
    use ProjectAwareTrait;

    public function __construct()
    {
        $this->loadProjectConfig();
    }

    private function jsonResponse(array $data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    #[Route('/api/v1/issues', method: 'GET')]
    public function listIssues()
    {
        $projectId = $_GET['projectId'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $this->jsonResponse(['error' => 'Project not found'], 404);
        }

        $project = $this->config['projects'][$projectId];
        $issuesDir = $this->getIssuesDir($project);

        $allIssues = $this->loadAllIssues($issuesDir);

        // Filters
        $status = $_GET['status'] ?? null;
        $type = $_GET['type'] ?? null;
        $assignee = $_GET['assignee'] ?? null;
        $milestone = $_GET['milestone_id'] ?? null;

        if ($status) $allIssues = array_values(array_filter($allIssues, fn($i) => ($i['status'] ?? 'open') === $status));
        if ($type) $allIssues = array_values(array_filter($allIssues, fn($i) => ($i['type'] ?? 'task') === $type));
        if ($assignee) $allIssues = array_values(array_filter($allIssues, fn($i) => in_array($assignee, $i['assignees'] ?? [])));
        if ($milestone) $allIssues = array_values(array_filter($allIssues, fn($i) => ($i['milestone_id'] ?? null) === $milestone));

        // Pagination
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        $total = count($allIssues);
        $issues = array_slice($allIssues, ($page - 1) * $perPage, $perPage);

        $this->jsonResponse([
            'total' => $total, 'page' => $page, 'per_page' => $perPage,
            'issues' => $issues,
        ]);
    }

    #[Route('/api/v1/issues/show', method: 'GET')]
    public function getIssue()
    {
        $projectId = $_GET['projectId'] ?? null;
        $issueId = $_GET['issueId'] ?? null;

        if (!$projectId || !$issueId || !isset($this->config['projects'][$projectId])) {
            $this->jsonResponse(['error' => 'Not found'], 404);
        }

        $project = $this->config['projects'][$projectId];
        $issuesDir = $this->getIssuesDir($project);
        $issue = $this->loadIssue($issuesDir, $issueId);

        if (!$issue) $this->jsonResponse(['error' => 'Issue not found'], 404);

        $this->jsonResponse(['issue' => $issue]);
    }

    #[Route('/api/v1/issues/create', method: 'POST')]
    public function createIssue()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $projectId = $input['project_id'] ?? '';
        $title = strip_tags($input['title'] ?? '');

        if (!$projectId || !$title || !isset($this->config['projects'][$projectId])) {
            $this->jsonResponse(['error' => 'Invalid input'], 400);
        }

        $project = $this->config['projects'][$projectId];
        $issuesDir = $this->getIssuesDir($project);

        $issueId = 'issue_' . time() . '_' . bin2hex(random_bytes(4));
        $issueData = [
            'title' => $title,
            'description' => strip_tags($input['description'] ?? ''),
            'author' => $input['author'] ?? 'API',
            'type' => $input['type'] ?? 'task',
            'status' => 'open',
            'priority' => $input['priority'] ?? 'medium',
            'labels' => $input['labels'] ?? [],
            'assignees' => $input['assignees'] ?? [],
            'team_id' => $input['team_id'] ?? null,
            'milestone_id' => $input['milestone_id'] ?? null,
            'due_date' => $input['due_date'] ?? null,
            'created_at' => time(),
            'updated_at' => time(),
            'comments' => [],
            'time_logs' => [],
        ];

        $this->saveIssue($issuesDir, $issueId, $issueData);
        $this->logActivity($issuesDir, 'issue.created', $issueData['author'], $issueId, "Created '{$title}' via API");

        $this->jsonResponse(['id' => $issueId, 'issue' => $issueData], 201);
    }

    #[Route('/api/v1/milestones', method: 'GET')]
    public function listMilestones()
    {
        $projectId = $_GET['projectId'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $this->jsonResponse(['error' => 'Project not found'], 404);
        }

        $project = $this->config['projects'][$projectId];
        $issuesDir = $this->getIssuesDir($project);
        $milestones = $this->loadMilestones($issuesDir);

        $this->jsonResponse(['milestones' => $milestones]);
    }

    #[Route('/api/v1/teams', method: 'GET')]
    public function listTeams()
    {
        $projectId = $_GET['projectId'] ?? null;
        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $this->jsonResponse(['error' => 'Project not found'], 404);
        }

        $project = $this->config['projects'][$projectId];
        $issuesDir = $this->getIssuesDir($project);
        $teams = $this->loadTeams($issuesDir);

        $this->jsonResponse(['teams' => $teams]);
    }

    // =========================================================================
    //  GIT WEBHOOK (Inbound)
    // =========================================================================

    #[Route('/api/webhook/git', method: 'POST')]
    public function gitWebhook()
    {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $projectId = $_GET['projectId'] ?? $payload['project_id'] ?? null;

        if (!$projectId || !isset($this->config['projects'][$projectId])) {
            $this->jsonResponse(['error' => 'Project not found'], 404);
        }

        $project = $this->config['projects'][$projectId];
        $issuesDir = $this->getIssuesDir($project);
        $storage = \App\SPPDocs\Storage\StorageFactory::create($project, $issuesDir);

        $processed = [];

        // Helper to resolve issue by string ID or integer number
        $resolveIssue = function ($ref) use ($storage) {
            if (is_numeric($ref)) {
                return $storage->findByNumber((int)$ref);
            }
            return $storage->find($ref);
        };

        // 1. Process Git Commits (Push Webhook)
        $commits = $payload['commits'] ?? [];
        foreach ($commits as $commit) {
            $message = $commit['message'] ?? '';
            $author = $commit['author']['name'] ?? $commit['author']['username'] ?? 'Git';
            $commitUrl = $commit['url'] ?? '';

            $parsedActions = \App\SPPDocs\Services\GitAutomationService::parseCommitMessage($message);

            // A. Fixes / Closes / Resolves
            foreach ($parsedActions['close_issues'] as $ref) {
                $issue = $resolveIssue($ref);
                if ($issue && ($issue['status'] ?? 'open') !== 'closed') {
                    $issue['status'] = 'closed';
                    $issue['comments'][] = [
                        'author' => 'Git System', 'type' => 'event', 'timestamp' => time(),
                        'content' => "Closed by commit from {$author}: \"{$message}\"" . ($commitUrl ? " ([view commit]({$commitUrl}))" : ''),
                    ];
                    $issue['updated_at'] = time();
                    $storage->save($issue['id'], $issue);
                    $this->logActivity($issuesDir, 'issue.closed', $author, $issue['id'], "Closed via git commit");
                    $processed[] = ['action' => 'closed', 'id' => $issue['id'], 'number' => $issue['number'] ?? null];
                }
            }

            // B. References / Mentions
            foreach ($parsedActions['ref_issues'] as $ref) {
                $issue = $resolveIssue($ref);
                if ($issue) {
                    $issue['comments'][] = [
                        'author' => 'Git System', 'type' => 'comment', 'timestamp' => time(),
                        'content' => "Referenced in commit by {$author}: \"{$message}\"" . ($commitUrl ? " ([view commit]({$commitUrl}))" : ''),
                    ];
                    $issue['updated_at'] = time();
                    $storage->save($issue['id'], $issue);
                    $processed[] = ['action' => 'referenced', 'id' => $issue['id']];
                }
            }

            // C. Smart Time Logging
            foreach ($parsedActions['time_logs'] as $tl) {
                $ref = $tl['issue'];
                $hours = $tl['hours'];
                $note = $tl['note'] ?: "Logged via commit by {$author}";
                $issue = $resolveIssue($ref);
                if ($issue) {
                    $issue['time_logs'][] = [
                        'user' => $author,
                        'hours' => $hours,
                        'description' => $note,
                        'date' => date('Y-m-d H:i:s'),
                        'timestamp' => time()
                    ];
                    $issue['updated_at'] = time();
                    $storage->save($issue['id'], $issue);
                    $this->logActivity($issuesDir, 'time.logged', $author, $issue['id'], "Logged {$hours}h via git commit");
                    $processed[] = ['action' => 'time_logged', 'id' => $issue['id'], 'hours' => $hours];
                }
            }
        }

        // 2. Process Pull Request Events (GitHub / GitLab PRs)
        $pr = $payload['pull_request'] ?? $payload['object_attributes'] ?? null;
        if ($pr) {
            $action = $payload['action'] ?? $pr['state'] ?? '';
            $prTitle = $pr['title'] ?? '';
            $prBody = $pr['body'] ?? $pr['description'] ?? '';
            $prUrl = $pr['html_url'] ?? $pr['url'] ?? '';
            $merged = !empty($pr['merged']) || $action === 'merged';

            $textToScan = $prTitle . "\n" . $prBody;
            if (preg_match_all('/(fix(?:es)?|close[sd]?|resolve[sd]?)\s+#([a-zA-Z0-9_]+)/i', $textToScan, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $ref = $match[2];
                    $issue = $resolveIssue($ref);
                    if ($issue) {
                        if ($merged) {
                            $issue['status'] = 'closed';
                            $issue['comments'][] = [
                                'author' => 'Git System', 'type' => 'event', 'timestamp' => time(),
                                'content' => "Closed by merged Pull Request: [{$prTitle}]({$prUrl})",
                            ];
                        } elseif ($action === 'opened') {
                            $issue['status'] = 'review';
                            $issue['comments'][] = [
                                'author' => 'Git System', 'type' => 'event', 'timestamp' => time(),
                                'content' => "Linked to Pull Request (moved to Review): [{$prTitle}]({$prUrl})",
                            ];
                        }
                        $issue['updated_at'] = time();
                        $storage->save($issue['id'], $issue);
                        $processed[] = ['action' => $merged ? 'pr_merged_close' : 'pr_review', 'id' => $issue['id']];
                    }
                }
            }
        }

        $this->jsonResponse(['success' => true, 'processed' => $processed, 'count' => count($processed)]);
    }
}
