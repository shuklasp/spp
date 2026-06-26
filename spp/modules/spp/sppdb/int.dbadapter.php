<?php

namespace SPPMod\SPPDB;

/**
 * Interface DBAdapter
 * Standard interface for all database drivers in SPP.
 */
interface DBAdapter
{
    public function query(string $sql, array $params = []): array;
    public function execute(string $sql, array $params = []): int;
    public function insert(string $table, array $data): bool;
    public function update(string $table, array $data, string $where, array $params = []): bool;
    public function delete(string $table, string $where, array $params = []): bool;
    public function tableExists(string $table): bool;
    public function getSchema(string $table): array;
    public function getLastInsertId(): ?string;
    public function beginTransaction(): bool;
    public function commit(): bool;
    public function rollBack(): bool;
    public function inTransaction(): bool;
}
