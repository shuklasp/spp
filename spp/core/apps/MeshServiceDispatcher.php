<?php
declare(strict_types=1);

namespace SPP\Core\Apps;

/**
 * Class MeshServiceDispatcher
 *
 * Ingress controller for the Sovereign Inter-App Service Mesh.
 * Endpoints:
 * - /spp-mesh/invoke/{app_id}/{endpoint} : Dispatches inter-app RPCs
 * - /spp-mesh/events/publish              : Emits events into SPPEvent bus
 * - /spp-mesh/events/stream               : Server-Sent Events (SSE) feed
 * - /spp-mesh/services                    : Discovers registered capabilities
 *
 * @package SPP\Core\Apps
 * @author Satya Prakash Shukla
 */
class MeshServiceDispatcher
{
    /**
     * Handles /spp-mesh/* requests.
     */
    public static function handle(string $qPath, string $method): void
    {
        $path = trim($qPath, '/');
        $segments = explode('/', $path);
        // segments[0] is 'spp-mesh'

        $action = $segments[1] ?? 'services';

        match ($action) {
            'invoke'   => self::handleInvoke(array_slice($segments, 2), $method),
            'events'   => self::handleEvents(array_slice($segments, 2), $method),
            'services' => self::handleServices(),
            default    => self::handleNotFound($action)
        };
    }

    /**
     * Handles /spp-mesh/invoke/{targetApp}/{endpoint...}
     *
     * @param array<string> $subSegments
     */
    protected static function handleInvoke(array $subSegments, string $method): void
    {
        if (empty($subSegments)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Missing target application in mesh invoke.']);
            return;
        }

        $targetAppId = array_shift($subSegments);
        $endpoint = '/' . implode('/', $subSegments);

        $app = ForeignAppRegistry::getApp($targetAppId);
        if (!$app) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Target application '{$targetAppId}' not found in registry."]);
            return;
        }

        if (!($app['enabled'] ?? true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Target application '{$targetAppId}' is disabled."]);
            return;
        }

        // Trace Propagation
        if (class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
            \SPPMod\SPPReport\W3CTraceContext::startTrace("SPPMesh:{$targetAppId}{$endpoint}");
        }

        // Forward request via SubpathGateway
        $resolution = [
            'app'           => $app,
            'app_id'        => $targetAppId,
            'match_type'    => 'mesh',
            'matched_route' => $app['route'],
            'sub_path'      => $endpoint,
        ];

        SubpathGateway::forward($resolution, $method);
    }

    /**
     * Handles /spp-mesh/events/*
     *
     * @param array<string> $subSegments
     */
    protected static function handleEvents(array $subSegments, string $method): void
    {
        $subAction = $subSegments[0] ?? 'publish';

        if ($subAction === 'publish') {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            $eventName = $data['event'] ?? null;
            $payload = $data['payload'] ?? [];
            $source = $data['source'] ?? 'mesh_client';

            if (!$eventName) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Missing event name.']);
                return;
            }

            // Fire across SPP event bus (dual firing: fireEvent + triggerHook)
            if (class_exists('\\SPP\\SPPEvent')) {
                \SPP\SPPEvent::fireEvent($eventName, $payload);
                \SPP\SPPEvent::triggerHook($eventName, $payload);
            }

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'event'   => $eventName,
                'source'  => $source,
                'message' => "Event '{$eventName}' broadcasted to SPP event bus."
            ]);
            return;
        }

        if ($subAction === 'stream') {
            // Server-Sent Events stream
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');

            echo "event: connected\n";
            echo "data: " . json_encode(['status' => 'connected', 'timestamp' => time()]) . "\n\n";
            flush();
            return;
        }

        self::handleNotFound($subAction);
    }

    /**
     * Handles /spp-mesh/services (Service Discovery Catalog).
     */
    protected static function handleServices(): void
    {
        $services = ForeignAppRegistry::listExposedServices();
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => true,
            'count'    => count($services),
            'services' => $services
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected static function handleNotFound(string $action): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "Unknown SPP Mesh action '{$action}'."]);
    }
}
