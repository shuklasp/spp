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

    public function getThread(string $threadId): ?array
    {
        $file = $this->forumsDir . '/' . $threadId . '.json';
        if (!file_exists($file)) return null;
        $thread = json_decode(file_get_contents($file), true);
        return is_array($thread) ? $thread : null;
    }

    public function createThread(string $categoryId, array $threadData): ?array
    {
        if (!is_dir($this->forumsDir)) {
            @mkdir($this->forumsDir, 0777, true);
        }
        $id = $threadData['id'] ?? ('thread_' . time() . '_' . bin2hex(random_bytes(4)));
        $threadData['id'] = $id;
        $threadData['category_id'] = $categoryId;
        $threadData['created_at'] = $threadData['created_at'] ?? time();
        $threadData['updated_at'] = time();
        $threadData['replies'] = $threadData['replies'] ?? [];
        $file = $this->forumsDir . '/' . $id . '.json';
        file_put_contents($file, json_encode($threadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $threadData;
    }

    public function postReply(string $threadId, array $replyData): ?array
    {
        $file = $this->forumsDir . '/' . $threadId . '.json';
        if (!file_exists($file)) return null;
        $thread = json_decode(file_get_contents($file), true);
        if (!$thread) return null;

        $replyId = $replyData['id'] ?? ('reply_' . time() . '_' . bin2hex(random_bytes(3)));
        $replyData['id'] = $replyId;
        $replyData['created_at'] = $replyData['created_at'] ?? time();
        $replyData['reactions'] = [];
        $thread['replies'][] = $replyData;
        $thread['updated_at'] = time();
        file_put_contents($file, json_encode($thread, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $replyData;
    }

    public function markSolution(string $threadId, string $replyId): bool
    {
        $file = $this->forumsDir . '/' . $threadId . '.json';
        if (!file_exists($file)) return false;
        $thread = json_decode(file_get_contents($file), true);
        if (!$thread) return false;

        $thread['solution_reply_id'] = $replyId;
        $thread['updated_at'] = time();
        return (bool) file_put_contents($file, json_encode($thread, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function addReaction(string $threadId, string $replyId, string $userId, string $reaction): bool
    {
        $file = $this->forumsDir . '/' . $threadId . '.json';
        if (!file_exists($file)) return false;
        $thread = json_decode(file_get_contents($file), true);
        if (!$thread) return false;

        foreach ($thread['replies'] as &$reply) {
            if (($reply['id'] ?? '') === $replyId) {
                if (!isset($reply['reactions'][$reaction])) {
                    $reply['reactions'][$reaction] = [];
                }
                if (!in_array($userId, $reply['reactions'][$reaction])) {
                    $reply['reactions'][$reaction][] = $userId;
                }
                break;
            }
        }
        $thread['updated_at'] = time();
        return (bool) file_put_contents($file, json_encode($thread, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
