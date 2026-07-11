<?php
namespace App\\testapp\\Serv;

/**
 * ============================================================================
 * ApiController — REST API Endpoints (JSON)
 * ============================================================================
 *
 * HOW THIS WORKS:
 * Controllers returning JSON should set the Content-Type header and
 * echo the JSON response. The framework does NOT auto-detect JSON.
 *
 * ROUTES (defined in pages.yml):
 *   GET  /api/v1/items      → index()     List all items
 *   GET  /api/v1/items/{id} → show(\$id)   Get single item
 *   POST /api/v1/items      → create()    Create new item
 *
 * AUTHENTICATION:
 * Use SPPAuth to validate API tokens or session cookies.
 * Set an auth validator via SPPAPI::setAuthValidator() in init.php.
 *
 * HOW TO ADD NEW ENDPOINTS:
 *   1. Add method to this class
 *   2. Add route in pages.yml:
 *      api/v1/newroute:
 *        controller: \\App\\testapp\\Serv\\ApiController@newMethod
 * ============================================================================
 */
class ApiController
{
    /**
     * GET /api/v1/items — List all items
     */
    public function index()
    {
        header('Content-Type: application/json');

        // Example: Query items from database
        \$items = [
            ['id' => 1, 'name' => 'Sample Item 1', 'status' => 'active', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'Sample Item 2', 'status' => 'completed', 'created_at' => date('Y-m-d H:i:s')],
        ];

        // Uncomment for real database queries:
        // \$db = new \\SPPMod\\SPPDB\\SPPDB();
        // \$items = \$db->execute_query('SELECT * FROM testapp_items ORDER BY id DESC');

        echo json_encode([
            'status' => 'ok',
            'data' => \$items,
            'meta' => [
                'total' => count(\$items),
                'page' => 1,
                'per_page' => 25,
            ]
        ]);
    }

    /**
     * GET /api/v1/items/{id} — Get single item
     */
    public function show()
    {
        header('Content-Type: application/json');

        // Route parameters are available via the page data
        \$pageData = \\SPPMod\\SPPView\\SPPGlobal::get('page');
        \$id = \$pageData['params'][0] ?? null;

        if (!\$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Item ID required']);
            return;
        }

        // Example: Return mock data
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'id' => (int)\$id,
                'name' => 'Item #' . \$id,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * API Documentation endpoint
     */
    public function docs()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'name' => 'testapp API',
            'version' => 'v1',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/items', 'description' => 'List all items'],
                ['method' => 'GET', 'path' => '/api/v1/items/{id}', 'description' => 'Get single item'],
                ['method' => 'POST', 'path' => '/api/v1/items', 'description' => 'Create new item'],
            ]
        ]);
    }
}