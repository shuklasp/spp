<?php

namespace App\SPPDocs\Contracts;

/**
 * Interface IssueTrackerDriverInterface
 * Defines the contract for issue tracking backends (Internal, GitHub, External Link, etc.)
 */
interface IssueTrackerDriverInterface
{
    /**
     * Get the driver's unique identifier (e.g. 'internal', 'github', 'external_link')
     */
    public function getIdentifier(): string;

    /**
     * Whether this driver outsources the user experience (e.g. redirects to Jira/GitHub)
     */
    public function isOutsourced(): bool;

    /**
     * Get the external or web URL for this issue tracker or a specific issue
     */
    public function getWebUrl(?string $issueId = null): string;

    /**
     * List issues matching given criteria
     */
    public function listIssues(array $filters = []): array;

    /**
     * Get a single issue by ID
     */
    public function getIssue(string $issueId): ?array;

    /**
     * Create a new issue
     */
    public function createIssue(array $data): array;

    /**
     * Update an existing issue
     */
    public function updateIssue(string $issueId, array $data): bool;
}
