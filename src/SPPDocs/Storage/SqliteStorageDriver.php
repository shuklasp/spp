<?php

namespace App\SPPDocs\Storage;

use App\SPPDocs\Contracts\IssueStorageInterface;
use PDO;

/**
 * SqliteStorageDriver
 * High-performance embedded ACID storage engine with Write-Ahead Logging (WAL) and B-Tree indexing.
 * Provides microsecond queries, indexed pagination, and concurrent read/write transactions.
 */
class SqliteStorageDriver implements IssueStorageInterface
{
    private string $dbPath;
    private ?PDO $pdo = null;

    public function __construct(string $dataDir)
    {
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        $this->dbPath = rtrim($dataDir, '/\\') . '/issues.sqlite';
        $this->initDb();
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA journal_mode = WAL;');
            $this->pdo->exec('PRAGMA synchronous = NORMAL;');
            $this->pdo->exec('PRAGMA busy_timeout = 5000;');
        }
        return $this->pdo;
    }

    private function initDb(): void
    {
        $pdo = $this->getPdo();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS issues (
                id TEXT PRIMARY KEY,
                number INTEGER UNIQUE,
                title TEXT NOT NULL,
                description TEXT,
                status TEXT NOT NULL DEFAULT 'open',
                type TEXT NOT NULL DEFAULT 'task',
                priority TEXT NOT NULL DEFAULT 'medium',
                milestone_id TEXT,
                parent_id TEXT,
                assignees TEXT,
                labels TEXT,
                due_date TEXT,
                created_by TEXT,
                created_at INTEGER NOT NULL,
                updated_at INTEGER NOT NULL,
                full_data TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_issues_status ON issues(status);
            CREATE INDEX IF NOT EXISTS idx_issues_type ON issues(type);
            CREATE INDEX IF NOT EXISTS idx_issues_priority ON issues(priority);
            CREATE INDEX IF NOT EXISTS idx_issues_milestone ON issues(milestone_id);
            CREATE INDEX IF NOT EXISTS idx_issues_parent ON issues(parent_id);
            CREATE INDEX IF NOT EXISTS idx_issues_created ON issues(created_at DESC);
        ");
    }

    public function loadAll(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $pdo = $this->getPdo();
        $sql = "SELECT id, number, title, status, type, priority, milestone_id, parent_id, assignees, labels, due_date, created_at, updated_at FROM issues WHERE 1=1";
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        if (!empty($filters['milestone_id'])) {
            $sql .= " AND milestone_id = :milestone_id";
            $params[':milestone_id'] = $filters['milestone_id'];
        }
        if (!empty($filters['parent_id'])) {
            $sql .= " AND parent_id = :parent_id";
            $params[':parent_id'] = $filters['parent_id'];
        }
        if (isset($filters['is_root']) && $filters['is_root']) {
            $sql .= " AND (parent_id IS NULL OR parent_id = '')";
        }
        if (!empty($filters['q'])) {
            $sql .= " AND (title LIKE :q OR id LIKE :q OR CAST(number AS TEXT) LIKE :q)";
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql .= " ORDER BY created_at DESC";

        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Decode JSON arrays for assignees and labels
        $issues = [];
        foreach ($rows as $row) {
            $row['assignees'] = json_decode($row['assignees'] ?? '[]', true) ?: [];
            $row['labels'] = json_decode($row['labels'] ?? '[]', true) ?: [];
            $row['number'] = (int) $row['number'];

            // Post-filtering for array fields (assignee, label)
            if (!empty($filters['assignee']) && !in_array($filters['assignee'], $row['assignees'])) {
                continue;
            }
            if (!empty($filters['label']) && !in_array($filters['label'], $row['labels'])) {
                continue;
            }

            $issues[] = $row;
        }

        return $issues;
    }

    public function find(string $id): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT full_data FROM issues WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $data = json_decode($row['full_data'], true);
        if ($data) {
            $data['id'] = $id;
        }
        return $data;
    }

    public function findByNumber(int $number): ?array
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT full_data FROM issues WHERE number = :number LIMIT 1");
        $stmt->execute([':number' => $number]);
        $row = $stmt->fetch();
        if (!$row) return null;

        return json_decode($row['full_data'], true);
    }

    public function save(string $id, array $data): bool
    {
        $pdo = $this->getPdo();

        if (isset($data['number'])) {
            $stmtCheck = $pdo->prepare("SELECT id FROM issues WHERE number = :num LIMIT 1");
            $stmtCheck->execute([':num' => (int)$data['number']]);
            $existingId = $stmtCheck->fetchColumn();
            if ($existingId && $existingId !== $id) {
                $data['number'] = $this->getNextIssueNumber();
            }
        } else {
            $data['number'] = $this->getNextIssueNumber();
        }

        $fullData = json_encode($data, JSON_UNESCAPED_SLASHES);
        $assignees = json_encode($data['assignees'] ?? [], JSON_UNESCAPED_SLASHES);
        $labels = json_encode($data['labels'] ?? [], JSON_UNESCAPED_SLASHES);

        $sql = "INSERT INTO issues (id, number, title, description, status, type, priority, milestone_id, parent_id, assignees, labels, due_date, created_by, created_at, updated_at, full_data)
                VALUES (:id, :number, :title, :description, :status, :type, :priority, :milestone_id, :parent_id, :assignees, :labels, :due_date, :created_by, :created_at, :updated_at, :full_data)
                ON CONFLICT(id) DO UPDATE SET
                    number = excluded.number,
                    title = excluded.title,
                    description = excluded.description,
                    status = excluded.status,
                    type = excluded.type,
                    priority = excluded.priority,
                    milestone_id = excluded.milestone_id,
                    parent_id = excluded.parent_id,
                    assignees = excluded.assignees,
                    labels = excluded.labels,
                    due_date = excluded.due_date,
                    updated_at = excluded.updated_at,
                    full_data = excluded.full_data";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':number' => (int) $data['number'],
            ':title' => $data['title'] ?? '',
            ':description' => $data['description'] ?? '',
            ':status' => $data['status'] ?? 'open',
            ':type' => $data['type'] ?? 'task',
            ':priority' => $data['priority'] ?? 'medium',
            ':milestone_id' => $data['milestone_id'] ?? null,
            ':parent_id' => $data['parent_id'] ?? null,
            ':assignees' => $assignees,
            ':labels' => $labels,
            ':due_date' => $data['due_date'] ?? null,
            ':created_by' => $data['created_by'] ?? 'admin',
            ':created_at' => $data['created_at'] ?? time(),
            ':updated_at' => time(),
            ':full_data' => $fullData,
        ]);
    }

    public function delete(string $id): bool
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM issues WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function count(array $filters = []): int
    {
        $pdo = $this->getPdo();
        $sql = "SELECT COUNT(*) as total FROM issues WHERE 1=1";
        $params = [];

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        if (!empty($filters['milestone_id'])) {
            $sql .= " AND milestone_id = :milestone_id";
            $params[':milestone_id'] = $filters['milestone_id'];
        }
        if (isset($filters['is_root']) && $filters['is_root']) {
            $sql .= " AND (parent_id IS NULL OR parent_id = '')";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch();
        return (int) ($res['total'] ?? 0);
    }

    public function getNextIssueNumber(): int
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->query("SELECT MAX(number) as max_num FROM issues");
        $res = $stmt->fetch();
        return ((int)($res['max_num'] ?? 0)) + 1;
    }

    public function search(string $query, array $filters = [], int $limit = 50): array
    {
        $filters['q'] = $query;
        return $this->loadAll($filters, $limit);
    }
}
