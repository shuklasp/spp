<?php
namespace SPPMod\SPPLive;

/**
 * Handles Server-Sent Events streaming for SPP Live.
 */
class SSEHandler {
    public static function stream(array $topics = ['global']): void {
        ServerDetector::applyStreamingHeaders();

        $authorizedTopics = [];
        foreach ($topics as $topic) {
            if (SPPLive::authorizeTopic($topic)) {
                $authorizedTopics[] = $topic;
            }
        }
        
        if (empty($authorizedTopics)) {
            // No authorized topics, close stream
            echo "event: spplive_error\n";
            echo "data: {\"status\": \"unauthorized\"}\n\n";
            return;
        }

        $engine = SPPLive::getEngine();

        // Send an initial heartbeat/connection success
        echo "event: spplive_connect\n";
        echo "data: {\"status\": \"connected\", \"topics\": " . json_encode($authorizedTopics) . "}\n\n";
        @ob_flush();
        @flush();

        $sleepTime = 1; // 1 second polling interval for SSE
        
        // Loop forever, sending events as they come in.
        // Client will automatically reconnect if this loop dies.
        while (!connection_aborted()) {
            $events = $engine->flush($topics);
            
            if (!empty($events)) {
                foreach ($events as $evt) {
                    echo "event: live_update\n";
                    echo "data: " . json_encode($evt) . "\n\n";
                }
                @ob_flush();
                @flush();
            }
            
            // Sleep to prevent CPU hogging
            sleep($sleepTime);
            
            // Periodically send a heartbeat comment to keep connection alive
            if (time() % 30 === 0) {
                echo ": heartbeat\n\n";
                @ob_flush();
                @flush();
            }
        }
    }
}
