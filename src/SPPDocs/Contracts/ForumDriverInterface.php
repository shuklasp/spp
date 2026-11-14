<?php

namespace App\SPPDocs\Contracts;

/**
 * Interface ForumDriverInterface
 * Defines the contract for forum/discussion backends (Internal, Discourse, External Link, etc.)
 */
interface ForumDriverInterface
{
    /**
     * Get the driver's unique identifier (e.g. 'internal', 'discourse', 'external_link')
     */
    public function getIdentifier(): string;

    /**
     * Whether this driver outsources the forum experience (redirects to Discourse/Discord/GitHub Discussions)
     */
    public function isOutsourced(): bool;

    /**
     * Get the web URL for the forum or a specific thread
     */
    public function getWebUrl(?string $threadId = null): string;

    /**
     * List discussion categories or topics
     */
    public function listCategories(): array;

    /**
     * List threads within a category
     */
    public function listThreads(?string $category = null): array;

    /**
     * Create a new discussion thread
     */
    public function createThread(string $categoryId, array $threadData): ?array;

    /**
     * Post a reply to an existing thread
     */
    public function postReply(string $threadId, array $replyData): ?array;

    /**
     * Mark a reply as the accepted solution
     */
    public function markSolution(string $threadId, string $replyId): bool;

    /**
     * Add an emoji reaction to a reply
     */
    public function addReaction(string $threadId, string $replyId, string $userId, string $reaction): bool;
}
