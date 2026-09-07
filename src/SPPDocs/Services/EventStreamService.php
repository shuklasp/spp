<?php

namespace App\SPPDocs\Services;

/**
 * EventStreamService
 * Real-time event streaming and pub/sub ring-buffer for SPPDocs.
 * Buffers issue, comment, and documentation events for Server-Sent Events (SSE) and live UI sync.
 */
class EventStreamService
{
    private static function getBufferFile(string $projectId): string
    {
        $dir = SPP_BASE_DIR . '/var/cache/SPPDocs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/events_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $projectId) . '.json';
    }

    /**
     * Publish an event to the project's event ring-buffer.
     */
    public static function publish(string $projectId, string $eventName, array $data): int
    {
        $file = self::getBufferFile($projectId);
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return 0;
        }

        $eventId = 1;
        if (flock($fp, LOCK_EX)) {
            $content = '';
            $filesize = filesize($file);
            if ($filesize > 0) {
                $content = fread($fp, $filesize);
            }
            $buffer = !empty($content) ? json_decode($content, true) : [];
            if (!is_array($buffer)) {
                $buffer = [];
            }

            $lastId = !empty($buffer) ? end($buffer)['id'] : 0;
            $eventId = $lastId + 1;

            $buffer[] = [
                'id' => $eventId,
                'time' => time(),
                'event' => $eventName,
                'data' => $data,
            ];

            // Retain last 50 events in the ring-buffer
            if (count($buffer) > 50) {
                $buffer = array_slice($buffer, -50);
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($buffer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        return $eventId;
    }

    /**
     * Retrieve all events occurring strictly after $lastEventId.
     */
    public static function getEventsSince(string $projectId, int $lastEventId): array
    {
        $file = self::getBufferFile($projectId);
        if (!file_exists($file)) {
            return [];
        }

        $content = @file_get_contents($file);
        if (!$content) {
            return [];
        }

        $buffer = json_decode($content, true) ?: [];
        $events = [];

        foreach ($buffer as $item) {
            if (($item['id'] ?? 0) > $lastEventId) {
                $events[] = $item;
            }
        }

        return $events;
    }
}
