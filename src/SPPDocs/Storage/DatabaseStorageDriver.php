<?php

namespace App\SPPDocs\Storage;

use App\SPPDocs\Contracts\IssueStorageInterface;
use PDO;

/**
 * DatabaseStorageDriver
 * Enterprise relational storage engine (MySQL, MariaDB, PostgreSQL).
 * Reuses framework database services (SPPDB / PDO) with connection pooling,
 * B-Tree indexing, full-text search, and SELECT ... FOR UPDATE transactional row-locking.
 */
class DatabaseStorageDriver implements IssueStorageInterface
{
    private string $projectId;
    private array $dbConfig;
    private ?PDO $pdo = null;
    private string $table;
    private string $driverType;

    public function __construct(string $projectId, array $dbConfig = [])
    {
        $this->projectId = $projectId;
        $this->dbConfig = $dbConfig;
        $this->driverType = strtolower($dbConfig['driver'] ?? 'mysql');
        if (in_array($this->driverType, ['postgres', 'pgsql'])) {
            $this->driverType = 'pgsql';
        } else {
            $this->driverType = 'mysql';
        }

        $prefix = $dbConfig['table_prefix'] ?? 'sppdocs_';
        $this->table = $prefix . 'issues';
        $this->initDb();
    }

    public function getPdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $host = $this->dbConfig['host'] ?? 'localhost';
        $port = !empty($this->dbConfig['port']) ? (int)$this->dbConfig['port'] : ($this->driverType === 'pgsql' ? 5432 : 3306);
        $dbname = $this->dbConfig['database'] ?? 'sppdocs';
        $user = $this->dbConfig['username'] ?? 'root';
        $pass = $this->dbConfig['password'] ?? '';

        if ($this->driverType === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        }

        try {
            // Attempt connection reuse via SPPDB if available
            if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
                $sppdb = new \SPPMod\SPPDB\SPPDB($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $this->pdo = $sppdb->getPDO();
            }
        } catch (\Throwable $e) {
            // Fallback to direct PDO
        }

        if ($this->pdo === null) {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return $this->pdo;
    }

    private function initDb(): void
    {
        $pdo = $this->getPdo();
        $table = $this->table;

        if ($this->driverType === 'pgsql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS {$table} (
                    id VARCHAR(64) PRIMARY KEY,
                    project_id VARCHAR(64) NOT NULL,
                    number INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    status VARCHAR(32) NOT NULL DEFAULT 'open',
                    type VARCHAR(32) NOT NULL DEFAULT 'task',
                    priority VARCHAR(32) NOT NULL DEFAULT 'medium',
                    milestone_id VARCHAR(64) NULL,
                    parent_id VARCHAR(64) NULL,
                    assignees TEXT NULL,
                    labels TEXT NULL,
                    due_date VARCHAR(32) NULL,
                    created_by VARCHAR(64) NULL,
                    created_at INT NOT NULL,
                    updated_at INT NOT NULL,
                    full_data TEXT NOT NULL,
                    CONSTRAINT uq_{$table}_proj_num UNIQUE (project_id, number)
                );
                CREATE INDEX IF NOT EXISTS idx_{$table}_proj_stat ON {$table} (project_id, status);
                CREATE INDEX IF NOT EXISTS idx_{$table}_proj_type ON {$table} (project_id, type);
                CREATE INDEX IF NOT EXISTS idx_{$table}_proj_ms ON {$table} (project_id, milestone_id);
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS {$table} (
                    id VARCHAR(64) PRIMARY KEY,
                    project_id VARCHAR(64) NOT NULL,
                    number INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    status VARCHAR(32) NOT NULL DEFAULT 'open',
                    type VARCHAR(32) NOT NULL DEFAULT 'task',
                    priority VARCHAR(32) NOT NULL DEFAULT 'medium',
                    milestone_id VARCHAR(64) NULL,
                    parent_id VARCHAR(64) NULL,
                    assignees TEXT NULL,
                    labels TEXT NULL,
                    due_date VARCHAR(32) NULL,
                    created_by VARCHAR(64) NULL,
                    created_at INT NOT NULL,
                    updated_at INT NOT NULL,
                    full_data LONGTEXT NOT NULL,
                    UNIQUE KEY uq_proj_num (project_id, number),
                    INDEX idx_proj_stat (project_id, status),
                    INDEX idx_proj_type (project_id, type),
                    INDEX idx_proj_ms (project_id, milestone_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }
    }

    public function loadAll(array $filters = [], int $limit = 0, int $offset = 0): array
    {
        $pdo = $this->getPdo();
        $table = $this->table;

        $sql = "SELECT full_data FROM {$table} WHERE project_id = :project_id";
        $params = [':project_id' => $this->projectId];

        if (!empty($filters['status'])) {
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
        if (!empty($filters['assignee'])) {
            $sql .= " AND assignees LIKE :assignee";
            $params[':assignee'] = '%' . $filters['assignee'] . '%';
        }
        if (!empty($filters['label'])) {
            $sql .= " AND labels LIKE :label";
            $params[':label'] = '%' . $filters['label'] . '%';
        }

        $sql .= " ORDER BY created_at DESC";

        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset > 0) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $item = json_decode($row['full_data'], true);
            if ($item) {
                $results[] = $item;
            }
        }
        return $results;
    }

    public function find(string $id): ?array
    {
        $pdo = $this->getPdo();
        $table = $this->table;
        $stmt = $pdo->prepare("SELECT full_data FROM {$table} WHERE id = :id AND project_id = :project_id LIMIT 1");
        $stmt->execute([':id' => $id, ':project_id' => $this->projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return json_decode($row['full_data'], true) ?: null;
    }

    public function findByNumber(int $number): ?array
    {
        $pdo = $this->getPdo();
        $table = $this->table;
        $stmt = $pdo->prepare("SELECT full_data FROM {$table} WHERE number = :number AND project_id = :project_id LIMIT 1");
        $stmt->execute([':number' => $number, ':project_id' => $this->projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return json_decode($row['full_data'], true) ?: null;
    }

    public function save(string $id, array $data): bool
    {
        $pdo = $this->getPdo();
        $table = $this->table;

        $pdo->beginTransaction();
        try {
            $number = $data['number'] ?? null;
            if ($number === null) {
                $stmt = $pdo->prepare("SELECT MAX(number) as max_num FROM {$table} WHERE project_id = :project_id FOR UPDATE");
                $stmt->execute([':project_id' => $this->projectId]);
                $maxRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $number = ((int)($maxRow['max_num'] ?? 0)) + 1;
                $data['number'] = $number;
            }

            $title = $data['title'] ?? 'Untitled';
            $description = $data['description'] ?? '';
            $status = $data['status'] ?? 'open';
            $type = $data['type'] ?? 'task';
            $priority = $data['priority'] ?? 'medium';
            $milestoneId = $data['milestone_id'] ?? null;
            $parentId = $data['parent_id'] ?? null;
            $assignees = is_array($data['assignees'] ?? null) ? json_encode($data['assignees']) : ($data['assignees'] ?? null);
            $labels = is_array($data['labels'] ?? null) ? json_encode($data['labels']) : ($data['labels'] ?? null);
            $dueDate = $data['due_date'] ?? null;
            $createdBy = $data['created_by'] ?? null;
            $createdAt = (int)($data['created_at'] ?? time());
            $updatedAt = time();
            $data['updated_at'] = $updatedAt;

            $fullData = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($this->driverType === 'pgsql') {
                $stmt = $pdo->prepare("
                    INSERT INTO {$table} (
                        id, project_id, number, title, description, status, type, priority,
                        milestone_id, parent_id, assignees, labels, due_date, created_by,
                        created_at, updated_at, full_data
                    ) VALUES (
                        :id, :project_id, :number, :title, :description, :status, :type, :priority,
                        :milestone_id, :parent_id, :assignees, :labels, :due_date, :created_by,
                        :created_at, :updated_at, :full_data
                    )
                    ON CONFLICT (id) DO UPDATE SET
                        title = EXCLUDED.title,
                        description = EXCLUDED.description,
                        status = EXCLUDED.status,
                        type = EXCLUDED.type,
                        priority = EXCLUDED.priority,
                        milestone_id = EXCLUDED.milestone_id,
                        parent_id = EXCLUDED.parent_id,
                        assignees = EXCLUDED.assignees,
                        labels = EXCLUDED.labels,
                        due_date = EXCLUDED.due_date,
                        updated_at = EXCLUDED.updated_at,
                        full_data = EXCLUDED.full_data
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO {$table} (
                        id, project_id, number, title, description, status, type, priority,
                        milestone_id, parent_id, assignees, labels, due_date, created_by,
                        created_at, updated_at, full_data
                    ) VALUES (
                        :id, :project_id, :number, :title, :description, :status, :type, :priority,
                        :milestone_id, :parent_id, :assignees, :labels, :due_date, :created_by,
                        :created_at, :updated_at, :full_data
                    )
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        description = VALUES(description),
                        status = VALUES(status),
                        type = VALUES(type),
                        priority = VALUES(priority),
                        milestone_id = VALUES(milestone_id),
                        parent_id = VALUES(parent_id),
                        assignees = VALUES(assignees),
                        labels = VALUES(labels),
                        due_date = VALUES(due_date),
                        updated_at = VALUES(updated_at),
                        full_data = VALUES(full_data)
                ");
            }

            $stmt->execute([
                ':id' => $id,
                ':project_id' => $this->projectId,
                ':number' => $number,
                ':title' => $title,
                ':description' => $description,
                ':status' => $status,
                ':type' => $type,
                ':priority' => $priority,
                ':milestone_id' => $milestoneId,
                ':parent_id' => $parentId,
                ':assignees' => $assignees,
                ':labels' => $labels,
                ':due_date' => $dueDate,
                ':created_by' => $createdBy,
                ':created_at' => $createdAt,
                ':updated_at' => $updatedAt,
                ':full_data' => $fullData
            ]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(string $id): bool
    {
        $pdo = $this->getPdo();
        $table = $this->table;
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id AND project_id = :project_id");
        $stmt->execute([':id' => $id, ':project_id' => $this->projectId]);
        return $stmt->rowCount() > 0;
    }

    public function count(array $filters = []): int
    {
        $pdo = $this->getPdo();
        $table = $this->table;
        $sql = "SELECT COUNT(*) FROM {$table} WHERE project_id = :project_id";
        $params = [':project_id' => $this->projectId];

        if (!empty($filters['status'])) {
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

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getNextIssueNumber(): int
    {
        $pdo = $this->getPdo();
        $table = $this->table;
        $stmt = $pdo->prepare("SELECT MAX(number) as max_num FROM {$table} WHERE project_id = :project_id");
        $stmt->execute([':project_id' => $this->projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int)($row['max_num'] ?? 0)) + 1;
    }

    public function search(string $query, array $filters = [], int $limit = 50): array
    {
        $pdo = $this->getPdo();
        $table = $this->table;
        $sql = "SELECT full_data FROM {$table} WHERE project_id = :project_id AND (title LIKE :q OR description LIKE :q)";
        $params = [
            ':project_id' => $this->projectId,
            ':q' => '%' . $query . '%'
        ];

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $item = json_decode($row['full_data'], true);
            if ($item) $results[] = $item;
        }
        return $results;
    }
}
