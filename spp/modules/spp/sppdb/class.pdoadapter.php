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

    /** @var \Closure|null */
    private $reconnector;
    protected bool $hasWritten = false;

    public function __construct(\PDO $pdo, ?\PDO $readPdo = null, ?\Closure $reconnector = null)
    {
        $this->pdo = $pdo;
        $this->readPdo = $readPdo;
        $this->reconnector = $reconnector;
    }

    public function updatePDO(\PDO $pdo, ?\PDO $readPdo = null): void
    {
        $this->pdo = $pdo;
        if ($readPdo !== null) {
            $this->readPdo = $readPdo;
        }
        $this->hasWritten = false;
    }

    protected function causedByLostConnection(\Throwable $e): bool
    {
        $message = $e->getMessage();
        return strpos($message, 'server has gone away') !== false ||
               strpos($message, 'no connection to the server') !== false ||
               strpos($message, 'Lost connection') !== false ||
               strpos($message, 'is dead or not enabled') !== false ||
               strpos($message, 'Error while sending') !== false ||
               strpos($message, 'decryption failed or bad record mac') !== false ||
               strpos($message, 'server closed the connection unexpectedly') !== false ||
               strpos($message, 'SSL connection has been closed unexpectedly') !== false ||
               strpos($message, 'Error writing data to the connection') !== false ||
               strpos($message, 'Resource deadlock avoided') !== false ||
               strpos($message, 'reset by peer') !== false ||
               strpos($message, 'Physical connection is not usable') !== false ||
               strpos($message, 'Communication link failure') !== false ||
               strpos($message, 'connection is no longer usable') !== false ||
               strpos($message, 'Login timeout expired') !== false ||
               strpos($message, 'SQLSTATE[HY000] [2002] Connection refused') !== false ||
               strpos($message, 'SQLSTATE[HY000] [2006]') !== false ||
               strpos($message, 'SQLSTATE[HY000] [2013]') !== false;
    }

    protected function runWithReconnect(\Closure $callback)
    {
        try {
            return $callback();
        } catch (\PDOException $e) {
            if ($this->causedByLostConnection($e) && $this->reconnector) {
                // Invoke reconnector
                call_user_func($this->reconnector, $this);
                // Retry once
                return $callback();
            }
            throw $e;
        }
    }

    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Gets the read connection. If a transaction is active, it safely falls back to the write connection
     * to prevent reading stale data. If a write has already occurred, it uses the write connection (Sticky Reads).
     */
    protected function getReadPDO(): \PDO
    {
        if ($this->readPdo !== null && !$this->inTransaction() && !$this->hasWritten) {
            return $this->readPdo;
        }
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): array
    {
        return $this->runWithReconnect(function () use ($sql, $params) {
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
        });
    }

    public function cursor(string $sql, array $params = []): \Generator
    {
        // runWithReconnect doesn't play perfectly with Generators because it returns early, 
        // but we can try to re-establish on the initial execute.
        $stmt = null;
        $this->runWithReconnect(function () use ($sql, $params, &$stmt) {
            $pdo = $this->getReadPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        });

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->runWithReconnect(function () use ($sql, $params) {
            $this->hasWritten = true;
            if (empty($params)) {
                return $this->pdo->exec($sql);
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        });
    }

    public function insert(string $table, array $data): bool
    {
        return $this->runWithReconnect(function () use ($table, $data) {
            $this->hasWritten = true;
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
        });
    }

    public function update(string $table, array $data, string $where, array $params = []): bool
    {
        return $this->runWithReconnect(function () use ($table, $data, $where, $params) {
            $this->hasWritten = true;
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
        });
    }

    public function delete(string $table, string $where, array $params = []): bool
    {
        return $this->runWithReconnect(function () use ($table, $where, $params) {
            $this->hasWritten = true;
            $safe_table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $sql = "DELETE FROM {$safe_table} WHERE {$where}";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        });
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
    protected int $transactions = 0;

    public function beginTransaction(): bool
    {
        if ($this->transactions == 0) {
            $this->transactions++;
            return $this->pdo->beginTransaction();
        }
        
        $this->transactions++;
        $this->pdo->exec("SAVEPOINT trans{$this->transactions}");
        return true;
    }

    public function commit(): bool
    {
        if ($this->transactions == 1) {
            $this->transactions = 0;
            return $this->pdo->commit();
        }
        
        if ($this->transactions > 1) {
            $this->transactions--;
            return true;
        }

        return false;
    }

    public function rollBack(): bool
    {
        if ($this->transactions == 1) {
            $this->transactions = 0;
            return $this->pdo->rollBack();
        }
        
        if ($this->transactions > 1) {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT trans{$this->transactions}");
            $this->transactions--;
            return true;
        }

        return false;
    }

    public function inTransaction(): bool
    {
        return $this->transactions > 0 || $this->pdo->inTransaction();
    }
}
