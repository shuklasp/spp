<?php
declare(strict_types=1);

namespace SPP;

use SPP\Core\Apps\ForeignAppRegistry;
use SPP\Core\Apps\AppSupervisor;
use SPP\Core\Apps\SubpathVirtualizer;

/**
 * Class Mesh
 *
 * Sovereign Inter-App Service Mesh Facade for SPP.
 * Allows native PHP controllers, CQRS handlers, workflows, and CLI tools
 * to directly invoke guest microservices (Python, Node.js, Go, Rust)
 * and publish/subscribe across the unified event bus.
 *
 * Usage:
 *   $prediction = \SPP\Mesh::call('ai_engine', '/api/predict', ['student_id' => 42]);
 *
 * @package SPP
 * @author Satya Prakash Shukla
 */
class Mesh
{
    /**
     * Invokes a service endpoint in a guest microservice or peer application.
     *
     * @param string $appId Target application identifier
     * @param string $endpoint Subpath endpoint (e.g. '/api/predict')
     * @param array<string, mixed>|null $payload Optional JSON-serializable request payload
     * @param string $method HTTP method (POST, GET, PUT, DELETE)
     * @param int $timeout Timeout in seconds
     * @return mixed Decoded JSON or string response
     * @throws \RuntimeException
     */
    public static function call(
        string $appId,
        string $endpoint,
        ?array $payload = null,
        string $method = 'POST',
        int $timeout = 10
    ): mixed {
        $app = ForeignAppRegistry::getApp($appId);
        if (!$app) {
            throw new \RuntimeException("Mesh Invocation Error: Application '{$appId}' not registered in etc/apps.yml.");
        }

        if (!($app['enabled'] ?? true)) {
            throw new \RuntimeException("Mesh Authorization Error: Application '{$appId}' is disabled.");
        }

        // Trace Propagation
        if (class_exists('\\SPPMod\\SPPReport\\W3CTraceContext')) {
            \SPPMod\SPPReport\W3CTraceContext::startTrace("SPPMesh:{$appId}{$endpoint}");
        }

        // 1. Ensure Application Daemon is Running
        $appType = strtolower((string)($app['type'] ?? 'node'));
        if (!in_array($appType, ['spp', 'static'], true)) {
            if (!AppSupervisor::isRunning($appId)) {
                $startRes = AppSupervisor::start($appId);
                if (!$startRes['success']) {
                    throw new \RuntimeException("Mesh Invocation Error: Could not boot daemon '{$appId}': " . ($startRes['error'] ?? 'Unknown error'));
                }
            }
        }

        $runtime = AppSupervisor::getAppRuntimeInfo($appId);
        $port = $runtime['port'] ?? null;
        if (!$port) {
            throw new \RuntimeException("Mesh Invocation Error: Could not resolve loopback port for '{$appId}'.");
        }

        // 2. Build Ingress Headers
        $cleanEp = '/' . ltrim($endpoint, '/');
        $url = "http://127.0.0.1:{$port}{$cleanEp}";

        $headers = SubpathVirtualizer::buildIngressHeaders($app, $app['route'], 'mesh', $runtime['token'] ?? null);
        $headers['Content-Type'] = 'application/json';
        $headers['X-SPP-Source'] = 'spp_core';

        $formattedHeaders = [];
        foreach ($headers as $k => $v) {
            $formattedHeaders[] = "{$k}: {$v}";
        }

        // 3. Dispatch HTTP Request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
                throw new \RuntimeException("Mesh Timeout Error: Request to '{$appId}' at {$cleanEp} timed out after {$timeout} seconds.");
            }
            throw new \RuntimeException("Mesh Invocation Error: Connection failed to '{$appId}' at {$url}: {$curlErr}");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("Mesh Service '{$appId}' returned HTTP {$httpCode}: {$response}");
        }

        $decoded = json_decode((string)$response, true);
        return $decoded !== null ? $decoded : $response;
    }

    /**
     * Publishes a domain event into the SPP event bus.
     */
    public static function publishEvent(string $event, array $payload = []): void
    {
        if (class_exists('\\SPP\\SPPEvent')) {
            \SPP\SPPEvent::fireEvent($event, $payload);
        }
    }

    /**
     * Discovers all services exposed across all applications.
     */
    public static function listServices(): array
    {
        return ForeignAppRegistry::listExposedServices();
    }

    /**
     * Returns list of all registered applications.
     */
    public static function listApps(): array
    {
        return ForeignAppRegistry::getAllApps();
    }
}
