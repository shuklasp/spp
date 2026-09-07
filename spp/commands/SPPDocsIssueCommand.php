<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use App\SPPDocs\Storage\StorageFactory;
use Symfony\Component\Yaml\Yaml;

/**
 * Class SPPDocsIssueCommand
 * Developer CLI tool for creating, viewing, listing, and closing SPPDocs issues from the terminal.
 */
class SPPDocsIssueCommand extends Command
{
    protected string $name = 'issue';
    protected string $description = 'Manage SPPDocs issues directly from the command line (create, list, view, close)';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $action = $this->getArgument($args, 0) ?? 'list';
        $params = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $params[$parts[0]] = $parts[1] ?? true;
            }
        }

        $projectId = $params['project'] ?? 'demo-app';
        $appDir = dirname(__DIR__, 2);
        $projectYml = $appDir . '/docs/' . $projectId . '/project.yml';
        if (!file_exists($projectYml)) {
            echo "❌ Error: Project '{$projectId}' not found at {$projectYml}\n";
            return;
        }

        $project = Yaml::parseFile($projectYml) ?: [];
        $issuesDir = $appDir . '/docs/' . $projectId . '/issues';
        $storage = StorageFactory::create($project, $issuesDir);

        switch ($action) {
            case 'create':
                $title = $params['title'] ?? null;
                if (!$title) {
                    echo "❌ Error: --title is required. Example: php spp.php issue create --project={$projectId} --title=\"Fix bug\"\n";
                    return;
                }
                $type = $params['type'] ?? 'task';
                $priority = $params['priority'] ?? 'medium';
                $assignee = $params['assignee'] ?? null;

                $data = [
                    'title' => $title,
                    'description' => $params['description'] ?? '',
                    'type' => $type,
                    'priority' => $priority,
                    'assignees' => $assignee ? [$assignee] : [],
                    'labels' => isset($params['labels']) ? explode(',', $params['labels']) : [],
                    'status' => 'open',
                    'author' => 'CLI',
                    'created_at' => time(),
                    'updated_at' => time(),
                    'comments' => [],
                    'time_logs' => [],
                    'number' => $storage->getNextIssueNumber(),
                ];
                $issueId = 'issue_' . time() . '_' . bin2hex(random_bytes(4));
                $data['id'] = $issueId;
                $storage->save($issueId, $data);
                \App\SPPDocs\Services\AuditLogService::append($issuesDir, 'issue.created', 'CLI', $issueId, "Created issue: {$title}");

                echo "✅ Issue #{$data['number']} created successfully!\n";
                echo "   Title:    {$data['title']}\n";
                echo "   ID:       {$data['id']}\n";
                echo "   Status:   {$data['status']}\n";
                echo "   Priority: {$data['priority']}\n";
                break;

            case 'view':
                $id = $params['id'] ?? null;
                if (!$id) {
                    echo "❌ Error: --id is required. Example: php spp.php issue view --project={$projectId} --id=1\n";
                    return;
                }
                $issue = is_numeric($id) ? $storage->findByNumber((int)$id) : $storage->find($id);
                if (!$issue) {
                    echo "❌ Error: Issue '{$id}' not found.\n";
                    return;
                }
                echo "========================================================\n";
                echo "🎯 Issue #" . ($issue['number'] ?? '') . ": " . $issue['title'] . "\n";
                echo "========================================================\n";
                echo "   Status:      " . strtoupper($issue['status'] ?? 'open') . "\n";
                echo "   Type:        " . ($issue['type'] ?? 'task') . "\n";
                echo "   Priority:    " . ($issue['priority'] ?? 'medium') . "\n";
                echo "   Assignees:   " . implode(', ', $issue['assignees'] ?? []) . "\n";
                echo "   Created At:  " . date('Y-m-d H:i:s', $issue['created_at'] ?? time()) . "\n";
                echo "\nDescription:\n";
                echo ($issue['description'] ?: '(No description provided)') . "\n";
                break;

            case 'close':
                $id = $params['id'] ?? null;
                if (!$id) {
                    echo "❌ Error: --id is required. Example: php spp.php issue close --project={$projectId} --id=1\n";
                    return;
                }
                $issue = is_numeric($id) ? $storage->findByNumber((int)$id) : $storage->find($id);
                if (!$issue) {
                    echo "❌ Error: Issue '{$id}' not found.\n";
                    return;
                }
                $issue['status'] = 'closed';
                $issue['updated_at'] = time();
                if (!empty($params['comment'])) {
                    $issue['comments'][] = [
                        'author' => 'CLI',
                        'type' => 'event',
                        'timestamp' => time(),
                        'content' => $params['comment']
                    ];
                }
                $storage->save($issue['id'], $issue);
                \App\SPPDocs\Services\AuditLogService::append($issuesDir, 'issue.closed', 'CLI', $issue['id'], "Closed issue #" . ($issue['number'] ?? ''));
                echo "✅ Issue #" . ($issue['number'] ?? $issue['id']) . " marked as CLOSED.\n";
                break;

            case 'list':
            default:
                $filters = [];
                if (isset($params['status'])) {
                    $filters['status'] = $params['status'];
                }
                $issues = $storage->loadAll($filters, 50);
                echo "🎯 Issues for project '{$projectId}' (" . count($issues) . " found):\n";
                echo str_repeat('-', 75) . "\n";
                printf("%-6s %-10s %-8s %-10s %-35s\n", "NUM", "STATUS", "TYPE", "PRIORITY", "TITLE");
                echo str_repeat('-', 75) . "\n";
                foreach ($issues as $iss) {
                    $num = '#' . ($iss['number'] ?? '-');
                    $st = strtoupper($iss['status'] ?? 'open');
                    $tp = $iss['type'] ?? 'task';
                    $pr = $iss['priority'] ?? 'med';
                    $ti = substr($iss['title'] ?? '', 0, 34);
                    printf("%-6s %-10s %-8s %-10s %-35s\n", $num, $st, $tp, $pr, $ti);
                }
                echo str_repeat('-', 75) . "\n";
                break;
        }
    }
}
