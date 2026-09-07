<?php

namespace App\SPPDocs\Storage;

use App\SPPDocs\Contracts\IssueStorageInterface;

/**
 * JsonStorageDriver
 * High-performance flat-file storage engine with incremental index caching (index.json)
 * Prevents $O(N)$ full-file reads on list/board queries while preserving 100% human-readable JSON files.
 */
class JsonStorageDriver implements IssueStorageInterface
{
    private string $issuesDir;
    private string $indexFile;
    private ?array $indexCache = null;

    public function __construct(string $issuesDir)
    {
        $this->issuesDir = rtrim($issuesDir, '/\\');
        $this->indexFile = $this->issuesDir . '/index.json';
    }

    private function getIndex(): array
    {
        if ($this->indexCache !== null) {
            return $this->indexCache;
        }

        if (file_exists($this->indexFile)) {
            $data = json_decode(file_get_contents($this->indexFile), true);
            if (is_array($data) && isset($data['issues'])) {
                $this->indexCache = $data;
                return $this->indexCache;
            }
        }

        return $this->rebuildIndex();
    }

    public function rebuildIndex(): array
    {
        $issues = [];
        $maxNumber = 0;

        if (is_dir($this->issuesDir)) {
            foreach (glob($this->issuesDir . '/issue_*.json') as $file) {
                $raw = @file_get_contents($file);
                if (!$raw) continue;
                $data = json_decode($raw, true);
                if (!$data) continue;

                $id = basename($file, '.json');
                $number = $data['number'] ?? null;
                if ($number === null) {
                    // Assign number sequentially if missing
                    $maxNumber++;
                    $number = $maxNumber;
                    $data['number'] = $number;
                    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
                } else {
                    $maxNumber = max($maxNumber, (int) $number);
                }

                $issues[$id] = [
                    'id' => $id,
                    'number' => (int) $number,
                    'title' => $data['title'] ?? '',
                    'status' => $data['status'] ?? 'open',
                    'type' => $data['type'] ?? 'task',
                    'priority' => $data['priority'] ?? 'medium',
                    'assignees' => $data['assignees'] ?? [],
                    'labels' => $data['labels'] ?? [],
                    'milestone_id' => $data['milestone_id'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                    'parent_id' => $data['parent_id'] ?? null,
                    'created_at' => $data['created_at'] ?? time(),
                    'updated_at' => $data['updated_at'] ?? time(),
                ];
            }
        }

        $index = [
            'version' => 1,
            'next_number' => $maxNumber + 1,
            'updated_at' => time(),
            'issues' => $issues
        ];

        $this->saveIndex($index);
        $this->indexCache = $index;
        return $index;
    }

    private function saveIndex(array $index): void
    {
        if (!is_dir($this->issuesDir)) {
            @mkdir($this->issuesDir, 0777, true);
        }
        @file_put_contents($this->indexFile, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $this->indexCache = $index;
    }

    public function loadAll(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $index = $this->getIndex();
        $issues = array_values($index['issues'] ?? []);

        // Filter issues using the fast in-memory index
        if (!empty($filters)) {
            $issues = array_filter($issues, function ($issue) use ($filters) {
                if (isset($filters['status']) && $filters['status'] !== 'all' && ($issue['status'] ?? 'open') !== $filters['status']) {
                    return false;
                }
                if (!empty($filters['type']) && ($issue['type'] ?? 'task') !== $filters['type']) {
                    return false;
                }
                if (!empty($filters['priority']) && ($issue['priority'] ?? 'medium') !== $filters['priority']) {
                    return false;
                }
                if (!empty($filters['assignee']) && !in_array($filters['assignee'], $issue['assignees'] ?? [])) {
                    return false;
                }
                if (!empty($filters['label']) && !in_array($filters['label'], $issue['labels'] ?? [])) {
                    return false;
                }
                if (!empty($filters['milestone_id']) && ($issue['milestone_id'] ?? '') !== $filters['milestone_id']) {
                    return false;
                }
                if (!empty($filters['parent_id']) && ($issue['parent_id'] ?? null) !== $filters['parent_id']) {
                    return false;
                }
                if (isset($filters['is_root']) && $filters['is_root'] && !empty($issue['parent_id'])) {
                    return false;
                }
                if (!empty($filters['q'])) {
                    $q = strtolower($filters['q']);
                    if (str_contains(strtolower($issue['title'] ?? ''), $q) === false &&
                        str_contains((string)($issue['number'] ?? ''), $q) === false) {
                        return false;
                    }
                }
                return true;
            });
        }

        // Sort descending by created_at
        usort($issues, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));

        if ($limit > 0) {
            return array_slice($issues, $offset, $limit);
        }

        return array_values($issues);
    }

    public function find(string $id): ?array
    {
        $file = $this->issuesDir . '/' . basename($id) . '.json';
        if (!file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data) return null;
        $data['id'] = basename($id);
        return $data;
    }

    public function findByNumber(int $number): ?array
    {
        $index = $this->getIndex();
        foreach ($index['issues'] as $id => $item) {
            if (($item['number'] ?? null) === $number) {
                return $this->find($id);
            }
        }
        return null;
    }

    public function save(string $id, array $data): bool
    {
        if (!is_dir($this->issuesDir)) {
            @mkdir($this->issuesDir, 0777, true);
        }

        $index = $this->getIndex();
        $isNew = !isset($index['issues'][$id]);

        if (!isset($data['number'])) {
            $data['number'] = $index['next_number'] ?? 1;
            $index['next_number'] = $data['number'] + 1;
        }

        $file = $this->issuesDir . '/' . basename($id) . '.json';
        $savedData = $data;
        unset($savedData['id']);

        $ok = @file_put_contents($file, json_encode($savedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($ok === false) return false;

        // Update incremental index
        $index['issues'][$id] = [
            'id' => $id,
            'number' => (int) $data['number'],
            'title' => $data['title'] ?? '',
            'status' => $data['status'] ?? 'open',
            'type' => $data['type'] ?? 'task',
            'priority' => $data['priority'] ?? 'medium',
            'assignees' => $data['assignees'] ?? [],
            'labels' => $data['labels'] ?? [],
            'milestone_id' => $data['milestone_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'created_at' => $data['created_at'] ?? time(),
            'updated_at' => time(),
        ];
        $index['updated_at'] = time();

        $this->saveIndex($index);
        return true;
    }

    public function delete(string $id): bool
    {
        $file = $this->issuesDir . '/' . basename($id) . '.json';
        if (file_exists($file)) {
            @unlink($file);
        }

        $index = $this->getIndex();
        if (isset($index['issues'][$id])) {
            unset($index['issues'][$id]);
            $index['updated_at'] = time();
            $this->saveIndex($index);
        }

        return true;
    }

    public function count(array $filters = []): int
    {
        return count($this->loadAll($filters));
    }

    public function getNextIssueNumber(): int
    {
        $index = $this->getIndex();
        return $index['next_number'] ?? 1;
    }

    public function search(string $query, array $filters = [], int $limit = 50): array
    {
        $filters['q'] = $query;
        return $this->loadAll($filters, $limit);
    }
}
