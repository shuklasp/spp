<?php

namespace App\SPPDocs\Drivers\Forums;

use App\SPPDocs\Contracts\ForumDriverInterface;
use SPP\App;

/**
 * InternalForumDriver
 * Native self-hosted flat-file forum driver for SPPDocs
 */
class InternalForumDriver implements ForumDriverInterface
{
    private string $forumsDir;
    private string $projectId;

    public function __construct(array $config = [])
    {
        $this->forumsDir = $config['forums_dir'] ?? '';
        $this->projectId = $config['project_id'] ?? '';
    }

    public function getIdentifier(): string
    {
        return 'internal';
    }

    public function isOutsourced(): bool
    {
        return false;
    }

    public function getWebUrl(?string $threadId = null): string
    {
        $base = App::url('forums') . '?projectId=' . urlencode($this->projectId);
        if ($threadId) {
            return App::url('forums/thread') . '?projectId=' . urlencode($this->projectId) . '&threadId=' . urlencode($threadId);
        }
        return $base;
    }

    public function listCategories(): array
    {
        $categoriesFile = $this->forumsDir . '/categories.json';
        if (file_exists($categoriesFile)) {
            return json_decode(file_get_contents($categoriesFile), true) ?: [];
        }
        return [];
    }

    public function listThreads(?string $category = null): array
    {
        $threads = [];
        if (!is_dir($this->forumsDir)) return [];

        foreach (glob($this->forumsDir . '/thread_*.json') as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                if ($category && ($data['category_id'] ?? '') !== $category) continue;
                $data['id'] = basename($file, '.json');
                $threads[] = $data;
            }
        }
        return $threads;
    }
}
