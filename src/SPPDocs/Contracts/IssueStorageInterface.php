<?php

namespace App\SPPDocs\Contracts;

/**
 * IssueStorageInterface
 * Defines the contract for SPPDocs issue storage backends (Flat-File JSON, SQLite WAL, etc.)
 */
interface IssueStorageInterface
{
    /**
     * Load filtered issues with pagination support.
     *
     * @param array $filters Associative array of filter criteria (status, type, priority, assignee, milestone_id, label)
     * @param int $limit Maximum records to return (0 for all)
     * @param int $offset Offset for pagination
     * @return array List of issues
     */
    public function loadAll(array $filters = [], int $limit = 0, int $offset = 0): array;

    /**
     * Find a single issue by its string identifier (e.g. 'issue_1725368000_a1b2').
     */
    public function find(string $id): ?array;

    /**
     * Find a single issue by its sequential integer number (e.g. 12 for #12).
     */
    public function findByNumber(int $number): ?array;

    /**
     * Save (insert or update) an issue.
     */
    public function save(string $id, array $data): bool;

    /**
     * Delete an issue.
     */
    public function delete(string $id): bool;

    /**
     * Count issues matching filters.
     */
    public function count(array $filters = []): int;

    /**
     * Retrieve the next sequential integer issue number for this project.
     */
    public function getNextIssueNumber(): int;

    /**
     * Full-text search across issue title and description.
     */
    public function search(string $query, array $filters = [], int $limit = 50): array;
}
