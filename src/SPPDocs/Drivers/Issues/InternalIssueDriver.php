<?php

namespace App\SPPDocs\Drivers\Issues;

use App\SPPDocs\Contracts\IssueTrackerDriverInterface;
use App\SPPDocs\Contracts\IssueStorageInterface;
use App\SPPDocs\Storage\StorageFactory;
use SPP\App;

/**
 * InternalIssueDriver
 * Self-hosted issue tracker driver for SPPDocs delegating to the configured IssueStorageInterface
 * (JsonStorageDriver with incremental index or SqliteStorageDriver with WAL mode).
 */
class InternalIssueDriver implements IssueTrackerDriverInterface
{
    private string $issuesDir;
    private string $projectId;
    private array $project;
    private IssueStorageInterface $storage;

    public function __construct(array $config = [])
    {
        $this->issuesDir = $config['issues_dir'] ?? '';
        $this->projectId = $config['project_id'] ?? '';
        $this->project = $config['project'] ?? [];
        $this->storage = StorageFactory::create($this->project, $this->issuesDir);
    }

    public function getIdentifier(): string
    {
        return 'internal';
    }

    public function isOutsourced(): bool
    {
        return false;
    }

    public function getWebUrl(?string $issueId = null): string
    {
        $base = App::url('issues') . '?projectId=' . urlencode($this->projectId);
        if ($issueId) {
            return App::url('issues/view') . '?projectId=' . urlencode($this->projectId) . '&issueId=' . urlencode($issueId);
        }
        return $base;
    }

    public function listIssues(array $filters = []): array
    {
        return $this->storage->loadAll($filters);
    }

    public function getIssue(string $issueId): ?array
    {
        if (is_numeric($issueId)) {
            return $this->storage->findByNumber((int)$issueId);
        }
        return $this->storage->find($issueId);
    }

    public function createIssue(array $data): array
    {
        $issueId = 'issue_' . time() . '_' . bin2hex(random_bytes(4));
        $data['id'] = $issueId;
        $data['created_at'] = time();
        $data['updated_at'] = time();
        if (!isset($data['status'])) $data['status'] = 'open';
        if (!isset($data['comments'])) $data['comments'] = [];
        if (!isset($data['time_logs'])) $data['time_logs'] = [];
        if (!isset($data['number'])) $data['number'] = $this->storage->getNextIssueNumber();

        $this->storage->save($issueId, $data);
        return $data;
    }

    public function updateIssue(string $issueId, array $data): bool
    {
        $existing = $this->getIssue($issueId);
        if (!$existing) return false;

        $merged = array_merge($existing, $data);
        $merged['updated_at'] = time();

        return $this->storage->save($issueId, $merged);
    }

    public function getStorage(): IssueStorageInterface
    {
        return $this->storage;
    }
}
