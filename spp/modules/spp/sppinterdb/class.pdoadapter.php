<?php
namespace SPPMod\SPPInterDB;

/**
 * Class PDOAdapter
 * Adapter for standard SQL databases using PDO.
 */
class PDOAdapter implements DBAdapter
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        if (empty($params)) {
            $stmt = $this->pdo->query($sql);
            return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): int
    {
        if (empty($params)) {
            return $this->pdo->exec($sql);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $table, array $data): bool
    {
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    public function update(string $table, array $data, string $where, array $params = []): bool
    {
        $set = [];
        foreach ($data as $col => $val) $set[] = "{$col} = ?";
        $setStr = implode(', ', $set);
        $sql = "UPDATE {$table} SET {$setStr} WHERE {$where}";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_merge(array_values($data), $params));
    }

    public function delete(string $table, string $where, array $params = []): bool
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function tableExists(string $table): bool
    {
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $res = $this->pdo->query("SHOW TABLES LIKE '{$safe_table}'");
        return $res && $res->rowCount() > 0;
    }

    public function getSchema(string $table): array
    {
        $res = $this->query("DESCRIBE {$table}");
        $columns = [];
        foreach ($res as $row) {
            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'null' => $row['Null'] === 'YES',
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
        return ['columns' => $columns];
    }

    public function getLastInsertId(): ?string
    {
        return $this->pdo->lastInsertId();
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
}
