<?php

namespace App\SPPDocs\Drivers\Forums;

use App\SPPDocs\Contracts\ForumDriverInterface;

/**
 * ExternalForumDriver
 * Outsources discussions to external forums (Discourse, Discord, GitHub Discussions, Flarum, etc.)
 */
class ExternalForumDriver implements ForumDriverInterface
{
    private string $baseUrl;

    public function __construct(array $config = [])
    {
        $this->baseUrl = $config['url'] ?? $config['link'] ?? '';
    }

    public function getIdentifier(): string
    {
        return 'external_link';
    }

    public function isOutsourced(): bool
    {
        return true;
    }

    public function getWebUrl(?string $threadId = null): string
    {
        if ($threadId && !empty($this->baseUrl)) {
            return rtrim($this->baseUrl, '/') . '/' . ltrim($threadId, '/');
        }
        return $this->baseUrl;
    }

    public function listCategories(): array
    {
        return [];
    }

    public function listThreads(?string $category = null): array
    {
        return [];
    }
}
