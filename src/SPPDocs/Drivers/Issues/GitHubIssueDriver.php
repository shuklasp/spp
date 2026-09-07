<?php

namespace App\SPPDocs\Drivers\Issues;

use App\SPPDocs\Contracts\IssueTrackerDriverInterface;
use SPPMod\SPPCache\SPPCacheManager;

/**
 * GitHubIssueDriver
 * Integrates directly with the GitHub Issues REST API
 */
class GitHubIssueDriver implements IssueTrackerDriverInterface
{
    private string $owner;
    private string $repo;
    private ?string $token;

    public function __construct(array $config = [])
    {
        $this->owner = $config['owner'] ?? '';
        $this->repo = $config['repo'] ?? '';
        $rawToken = $config['api_token'] ?? $config['token'] ?? null;
        
        if ($rawToken && str_starts_with($rawToken, 'env:')) {
            $envVar = substr($rawToken, 4);
            $this->token = getenv($envVar) ?: null;
        } else {
            $this->token = $rawToken;
        }
    }

    public function getIdentifier(): string
    {
        return 'github';
    }

    public function isOutsourced(): bool
    {
        return false;
    }

    public function getWebUrl(?string $issueId = null): string
    {
        $base = "https://github.com/{$this->owner}/{$this->repo}/issues";
        if ($issueId) {
            $cleanId = preg_replace('/[^0-9]/', '', $issueId);
            return $base . '/' . $cleanId;
        }
        return $base;
    }

    private function apiRequest(string $endpoint, string $method = 'GET', ?array $data = null): ?array
    {
        $url = "https://api.github.com/repos/{$this->owner}/{$this->repo}/" . ltrim($endpoint, '/');
        $ch = curl_init($url);
        
        $headers = [
            'User-Agent: SPPDocs-Hub/1.0',
            'Accept: application/vnd.github+json',
        ];
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300 && $res) {
            return json_decode($res, true);
        }

        return null;
    }

    public function listIssues(array $filters = []): array
    {
        $cacheKey = "gh_issues_{$this->owner}_{$this->repo}";
        
        // Cache GitHub API response for 60 seconds to prevent rate limiting
        $cached = SPPCacheManager::get($cacheKey, function () {
            $raw = $this->apiRequest('issues?state=all&per_page=100');
            if (!is_array($raw)) return [];

            $normalized = [];
            foreach ($raw as $ghIssue) {
                // Ignore pull requests returned by GitHub issues endpoint
                if (isset($ghIssue['pull_request'])) continue;

                $labels = array_map(fn($l) => $l['name'], $ghIssue['labels'] ?? []);
                $assignees = array_map(fn($a) => $a['login'], $ghIssue['assignees'] ?? []);

                $normalized[] = [
                    'id' => (string) $ghIssue['number'],
                    'title' => $ghIssue['title'],
                    'description' => $ghIssue['body'] ?? '',
                    'status' => ($ghIssue['state'] === 'closed') ? 'closed' : 'open',
                    'author' => $ghIssue['user']['login'] ?? 'Ghost',
                    'type' => in_array('bug', $labels) ? 'bug' : (in_array('enhancement', $labels) ? 'feature' : 'task'),
                    'priority' => 'medium',
                    'labels' => $labels,
                    'assignees' => $assignees,
                    'created_at' => strtotime($ghIssue['created_at']),
                    'updated_at' => strtotime($ghIssue['updated_at']),
                    'web_url' => $ghIssue['html_url'],
                    'comments_count' => $ghIssue['comments'] ?? 0,
                ];
            }
            return $normalized;
        }, 60, ['github_issues']);

        return is_array($cached) ? $cached : [];
    }

    public function getIssue(string $issueId): ?array
    {
        $cleanId = preg_replace('/[^0-9]/', '', $issueId);
        $ghIssue = $this->apiRequest("issues/{$cleanId}");
        if (!$ghIssue) return null;

        $labels = array_map(fn($l) => $l['name'], $ghIssue['labels'] ?? []);
        $assignees = array_map(fn($a) => $a['login'], $ghIssue['assignees'] ?? []);

        return [
            'id' => (string) $ghIssue['number'],
            'title' => $ghIssue['title'],
            'description' => $ghIssue['body'] ?? '',
            'status' => ($ghIssue['state'] === 'closed') ? 'closed' : 'open',
            'author' => $ghIssue['user']['login'] ?? 'Ghost',
            'type' => in_array('bug', $labels) ? 'bug' : (in_array('enhancement', $labels) ? 'feature' : 'task'),
            'priority' => 'medium',
            'labels' => $labels,
            'assignees' => $assignees,
            'created_at' => strtotime($ghIssue['created_at']),
            'updated_at' => strtotime($ghIssue['updated_at']),
            'web_url' => $ghIssue['html_url'],
            'comments' => [],
        ];
    }

    public function createIssue(array $data): array
    {
        $payload = [
            'title' => $data['title'] ?? '',
            'body' => $data['description'] ?? '',
            'labels' => $data['labels'] ?? [],
        ];

        $res = $this->apiRequest('issues', 'POST', $payload);
        if ($res && isset($res['number'])) {
            return ['id' => (string) $res['number'], 'web_url' => $res['html_url']];
        }

        return ['error' => 'Failed to create issue on GitHub'];
    }

    public function updateIssue(string $issueId, array $data): bool
    {
        $cleanId = preg_replace('/[^0-9]/', '', $issueId);
        $payload = [];
        if (isset($data['status'])) {
            $payload['state'] = ($data['status'] === 'closed') ? 'closed' : 'open';
        }
        if (isset($data['title'])) $payload['title'] = $data['title'];
        if (isset($data['description'])) $payload['body'] = $data['description'];

        $res = $this->apiRequest("issues/{$cleanId}", 'POST', $payload);
        return $res !== null;
    }
}
