<?php
namespace SPPMod\SPPLive;

/**
 * Handles Server-Sent Events streaming for SPP Live.
 */
class SSEHandler {
    public static function stream(array $topics = ['global']): void {
        try {
            ServerDetector::applyStreamingHeaders();
        } catch (StreamingDegradationException $e) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            echo "event: spplive_degrade\n";
            echo "data: {\"status\": \"degraded\", \"reason\": " . json_encode($e->getMessage()) . "}\n\n";
            @ob_flush();
            @flush();
            return;
        }

        // CRITICAL: Close PHP session before entering long-running streaming loop!
        // If left open, PHP holds an exclusive lock on the session file,
        // freezing all subsequent requests and UI interactions from this user.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $authorizedTopics = [];
        foreach ($topics as $topic) {
            if (is_string($topic) && SPPLive::authorizeTopic($topic)) {
                $authorizedTopics[] = $topic;
            }
        }
        
        if (empty($authorizedTopics)) {
            // No authorized topics, close stream
            echo "event: spplive_error\n";
            echo "data: {\"status\": \"unauthorized\"}\n\n";
            @ob_flush();
            @flush();
            return;
        }

        $engine = SPPLive::getEngine();

        // Send an initial heartbeat/connection success
        echo "event: spplive_connect\n";
        echo "data: {\"status\": \"connected\", \"topics\": " . json_encode($authorizedTopics) . "}\n\n";
        @ob_flush();
        @flush();

        $lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
        if ($lastEventId === 0 && isset($_GET['last_event_id'])) {
            $lastEventId = (int)$_GET['last_event_id'];
        }

        // If starting fresh without an ID, sync to the latest event ID to avoid flooding past history
        if ($lastEventId === 0 && method_exists($engine, 'getLatestEventId')) {
            $lastEventId = $engine->getLatestEventId();
        }

        $sleepTime = 1; // 1 second polling interval for SSE
        $loopCount = 0;
        
        // Loop forever, sending events as they come in.
        // Client will automatically reconnect if this loop dies.
        while (!connection_aborted()) {
            if (method_exists($engine, 'fetchSince')) {
                $events = $engine->fetchSince($lastEventId, $authorizedTopics);
            } else {
                $events = $engine->flush($authorizedTopics);
            }
            
            if (!empty($events)) {
                foreach ($events as $evt) {
                    $eventId = $evt['id'] ?? null;
                    if ($eventId !== null) {
                        $lastEventId = max($lastEventId, (int)$eventId);
                        echo "id: {$eventId}\n";
                    }
                    echo "event: live_update\n";
                    echo "data: " . json_encode($evt) . "\n\n";
                }
                @ob_flush();
                @flush();
            }
            
            // Sleep to prevent CPU hogging
            sleep($sleepTime);
            $loopCount++;
            
            // Periodically send a heartbeat comment to keep connection alive
            if ($loopCount % 30 === 0) {
                echo ": heartbeat\n\n";
                @ob_flush();
                @flush();
            }
        }
    }
}
