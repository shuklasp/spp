<?php

namespace App\SPPDocs\Controllers;

use SPPMod\SPPView\Attributes\Route;
use App\SPPDocs\Services\EventStreamService;

/**
 * EventStreamController
 * Provides real-time Server-Sent Events (SSE) and polling fallback for Kanban board & project sync.
 */
class EventStreamController extends \SPPMod\SPPView\ViewController
{
    #[Route('/issues/events', method: 'GET')]
    public function streamEvents()
    {
        $projectId = $_GET['project'] ?? $_GET['projectId'] ?? '';
        if (!$projectId) {
            http_response_code(400);
            echo "Missing project parameter.";
            exit;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Disable output buffering and configure SSE headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) 
            ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] 
            : (int)($_GET['last_id'] ?? 0);

        // Tell browser to reconnect after 1.5 seconds when cycle closes
        echo "retry: 1500\n";
        // Initial ping to confirm stream connection
        echo "event: ping\ndata: {\"status\":\"connected\",\"projectId\":\"" . htmlspecialchars($projectId, ENT_QUOTES) . "\"}\n\n";
        flush();

        // Stream active events for up to 6 seconds to eliminate PHP worker starvation
        $startTime = time();
        while (time() - $startTime < 6) {
            if (connection_aborted()) {
                break;
            }

            $events = EventStreamService::getEventsSince($projectId, $lastEventId);
            if (!empty($events)) {
                foreach ($events as $evt) {
                    $lastEventId = $evt['id'];
                    echo "id: " . $evt['id'] . "\n";
                    echo "event: " . $evt['event'] . "\n";
                    echo "data: " . json_encode($evt['data']) . "\n\n";
                }
                flush();
            }

            // Sleep 500ms between checks for ultra-responsive dispatch without CPU spin
            usleep(500000);
        }

        exit;
    }

    #[Route('/issues/poll', method: 'GET')]
    public function poll()
    {
        $projectId = $_GET['project'] ?? $_GET['projectId'] ?? '';
        $lastEventId = (int)($_GET['last_id'] ?? 0);

        header('Content-Type: application/json');
        if (!$projectId) {
            echo json_encode(['events' => [], 'last_id' => $lastEventId]);
            exit;
        }

        $events = EventStreamService::getEventsSince($projectId, $lastEventId);
        $maxId = $lastEventId;
        foreach ($events as $e) {
            if ($e['id'] > $maxId) {
                $maxId = $e['id'];
            }
        }

        echo json_encode([
            'events' => $events,
            'last_id' => $maxId,
        ]);
        exit;
    }
}
