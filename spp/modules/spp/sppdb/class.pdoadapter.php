<?php

namespace SPPMod\SPPDB;

/**
 * Class PDOAdapter
 * Adapter for standard SQL databases using PDO.
 */
class PDOAdapter implements DBAdapter
{
    private \PDO $pdo;
    private ?\PDO $readPdo;

    public function __construct(\PDO $pdo, ?\PDO $readPdo = null)
    {
        $this->pdo = $pdo;
        $this->readPdo = $readPdo;
    }

    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Gets the read connection. If a transaction is active, it safely falls back to the write connection
     * to prevent reading stale data.
     */
    protected function getReadPDO(): \PDO
    {
        if ($this->readPdo !== null && !$this->inTransaction()) {
            return $this->readPdo;
        }
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        $pdo = $this->getReadPDO();
        try {
            if (empty($params)) {
                $stmt = $pdo->query($sql);
                return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage() . " (SQL: " . $sql . ")", (int) $e->getCode(), $e);
        }
    }

    public function cursor(string $sql, array $params = []): \Generator
    {
        $pdo = $this->getReadPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
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
        $safeCols = [];
        foreach (array_keys($data) as $col) {
            $safeCols[] = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        }
        $cols = implode(', ', $safeCols);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sql = "INSERT INTO {$safe_table} ({$cols}) VALUES ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    public function update(string $table, array $data, string $where, array $params = []): bool
    {
        $set = [];
        foreach ($data as $col => $val) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $set[] = "{$safeCol} = ?";
        }
        $setStr = implode(', ', $set);
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sql = "UPDATE {$safe_table} SET {$setStr} WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_merge(array_values($data), $params));
    }

    public function delete(string $table, string $where, array $params = []): bool
    {
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sql = "DELETE FROM {$safe_table} WHERE {$where}";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function tableExists(string $table): bool
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        if ($driver === 'sqlite') {
            $res = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$safe_table}'");
        } else {
            $res = $this->pdo->query("SHOW TABLES LIKE '{$safe_table}'");
        }
        return $res && $res->fetch() !== false;
    }

    public function getSchema(string $table): array
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($driver === 'sqlite') {
            $res = $this->query("PRAGMA table_info({$safe_table})");
            $columns = [];
            foreach ($res as $row) {
                $columns[$row['name']] = [
                    'type' => $row['type'],
                    'null' => $row['notnull'] == 0,
                    'key' => $row['pk'] == 1 ? 'PRI' : '',
                    'default' => $row['dflt_value'],
                    'extra' => ''
                ];
            }
        } else {
            $res = $this->query("DESCRIBE {$safe_table}");
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
        }
        return ['columns' => $columns];
    }

    public function getLastInsertId(): ?string
    {
        return $this->pdo->lastInsertId();
    }
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
