<?php

namespace SPPMod\SPPInterDB;

use SPPMod\SPPXDB\SPP_XDB;

/**
 * Class XDBAdapter
 * Adapter for SPPXDB XML Database.
 */
class XDBAdapter implements DBAdapter
{
    private SPP_XDB $xdb;

    public function __construct(SPP_XDB $xdb)
    {
        $this->xdb = $xdb;
    }

    public function query(string $sql, array $params = []): array
    {
        $res = $this->xdb->querySQL($sql, $params);
        return is_array($res) ? $res : [];
    }

    public function execute(string $sql, array $params = []): int
    {
        $res = $this->xdb->querySQL($sql, $params);
        return is_array($res) ? count($res) : ($res === true ? 1 : 0);
    }

    public function insert(string $table, array $data): bool
    {
        error_log("XDBAdapter::insert into table: " . $table);
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})";
        return (bool)$this->xdb->querySQL($sql, array_values($data));
    }

    public function update(string $table, array $data, string $where, array $params = []): bool
    {
        $set = [];
        foreach ($data as $col => $val) {
            $set[] = "{$col} = ?";
        }
        $setStr = implode(', ', $set);
        $sql = "UPDATE {$table} SET {$setStr} WHERE {$where}";
        return (bool)$this->xdb->querySQL($sql, array_merge(array_values($data), $params));
    }

    public function delete(string $table, string $where, array $params = []): bool
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return (bool)$this->xdb->querySQL($sql, $params);
    }

    public function tableExists(string $table): bool
    {
        $tables = $this->xdb->querySQL("SHOW TABLES");
        foreach ($tables as $t) {
            if (current($t) === $table) {
                return true;
            }
        }
        return false;
    }

    public function getSchema(string $table): array
    {
        $res = $this->query("DESCRIBE {$table}");
        $columns = [];
        foreach ($res as $row) {
            $columns[$row['Field']] = [
                'type' => $row['Type'],
                'null' => $row['Null'] === 'YES' || $row['Null'] === 'true',
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
        return ['columns' => $columns];
    }

    public function getLastInsertId(): ?string
    {
        return (string)$this->xdb->getLastInsertId();
    }
    public function beginTransaction(): bool
    {
        return true;
    }
    public function commit(): bool
    {
        return true;
    }
    public function rollBack(): bool
    {
        return true;
    }
    public function inTransaction(): bool
    {
        return false;
    }
}
