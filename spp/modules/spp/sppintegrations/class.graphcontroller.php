<?php
namespace SPPMod\SPPIntegrations;

use SPP\MVC\Controllers\ViewController;

/**
 * Class IntegrationGraphController
 * 
 * Provides a unified GraphQL-style Supergraph endpoint, stitching together
 * data concurrently from multiple external drivers into a single JSON payload.
 */
class IntegrationGraphController extends ViewController
{
    /**
     * Endpoint: /api/integration/graphql
     * 
     * Accepts a simplified GraphQL query to orchestrate Data Federation.
     */
    public function handleGraphQuery(): void
    {
        header('Content-Type: application/json');

        // Parse incoming raw JSON (GraphQL query representation)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['query'])) {
            echo json_encode(['error' => 'Invalid query payload']);
            return;
        }

        $query = $input['query'];
        $response = [];

        // Simple Supergraph Resolver Logic
        // Resolves top-level blocks to specific drivers dynamically.
        foreach ($query as $node => $arguments) {
            try {
                // E.g., if node is 'moodle_courses', we extract 'moodle'
                $parts = explode('_', $node, 2);
                $driverAlias = $parts[0];
                $endpoint = $arguments['endpoint'] ?? '';

                if (empty($endpoint)) {
                    $response[$node] = ['error' => 'Missing endpoint argument'];
                    continue;
                }

                $driver = IntegrationFactory::getDriver($driverAlias);
                $response[$node] = $driver->fetchData($endpoint);

            } catch (\Exception $e) {
                $response[$node] = ['error' => $e->getMessage()];
            }
        }

        echo json_encode(['data' => $response]);
    }
}
