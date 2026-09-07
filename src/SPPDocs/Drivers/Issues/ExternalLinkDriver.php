<?php

namespace App\SPPDocs\Drivers\Issues;

use App\SPPDocs\Contracts\IssueTrackerDriverInterface;

/**
 * ExternalLinkDriver
 * Outsources issue tracking to external web systems (Jira, Linear, Bugzilla, Redmine, etc.)
 */
class ExternalLinkDriver implements IssueTrackerDriverInterface
{
    private string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['url'] ?? $config['link'] ?? '';
    }

    public function getIdentifier(): string
    {
        return 'external_link';
    }

    public function isOutsourced(): bool
    {
        return true;
    }

    public function getWebUrl(?string $issueId = null): string
    {
        if ($issueId && !empty($this->baseUrl)) {
            return rtrim($this->baseUrl, '/') . '/' . ltrim($issueId, '/');
        }
        return $this->baseUrl;
    }

    public function listIssues(array $filters = []): array
    {
        return [];
    }

    public function getIssue(string $issueId): ?array
    {
        return null;
    }

    public function createIssue(array $data): array
    {
        return ['error' => 'Managed externally at ' . $this->baseUrl];
    }

    public function updateIssue(string $issueId, array $data): bool
    {
        return false;
    }
}
