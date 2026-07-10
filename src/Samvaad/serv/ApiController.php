<?php
namespace App\Samvaad\Serv;

/**
 * ============================================================================
 * ApiController — REST API Endpoints (JSON)
 * ============================================================================
 *
 * HOW THIS WORKS:
 * Controllers returning JSON should set the Content-Type header and
 * echo the JSON response. The framework does NOT auto-detect JSON.
 *
 * ENTERPRISE CAPABILITIES:
 * Use $this->json($data, $statusCode) to return standardized JSON instantly.
 * Use $this->validate($rules) to validate incoming API request payloads.
 * Use $this->renderPartial($view) to return rendered external .html, .php, or .js partials for hypermedia APIs.
 *
 * ROUTES (defined in pages.yml):
 *   GET  /api/v1/items      → index()     List all items
 *   GET  /api/v1/items/{id} → show($id)   Get single item
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
 *        controller: \App\Samvaad\Serv\ApiController@newMethod
 * ============================================================================
 */
class ApiController extends \SPPMod\SPPView\ViewController
{
    /**
     * GET /api/v1/items — List all items
     */
    public function index()
    {
        // Example: Query items from database
        $items = [
            ['id' => 1, 'name' => 'Sample Item 1', 'status' => 'active', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'name' => 'Sample Item 2', 'status' => 'completed', 'created_at' => date('Y-m-d H:i:s')],
        ];

        // Uncomment for real database queries:
        // $db = new \SPPMod\SPPDB\SPPDB();
        // $items = $db->execute_query('SELECT * FROM Samvaad_items ORDER BY id DESC');

        // Example: If client requests HTML partial representation (e.g. HTMX hypermedia API)
        // if ($this->isHtmx()) {
        //     return $this->renderPartial('partials/api_item_list.html', ['items' => $items]);
        // }

        return $this->json([
            'status' => 'ok',
            'data' => $items,
            'meta' => [
                'total' => count($items),
                'page' => 1,
                'per_page' => 25,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/items/{id} — Get single item
     */
    public function show()
    {
        // Route parameters are available via the page data
        $pageData = \SPPMod\SPPView\SPPGlobal::get('page');
        $id = $pageData['params'][0] ?? null;

        if (!$id) {
            return $this->json(['status' => 'error', 'message' => 'Item ID required'], 400);
        }

        // Example: Return mock data
        return $this->json([
            'status' => 'ok',
            'data' => [
                'id' => (int)$id,
                'name' => 'Item #' . $id,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/items — Create new item demonstrating Enterprise Request Validation
     */
    public function create()
    {
        $valResult = $this->validate([
            'name' => ['required', 'min:3'],
            'status' => ['required']
        ]);

        if (!$valResult->isValid()) {
            return $this->json(['status' => 'error', 'errors' => $valResult->getErrors()], 422);
        }

        return $this->json(['status' => 'ok', 'message' => 'Item created successfully'], 201);
    }

    /**
     * API Documentation endpoint
     */
    public function docs()
    {
        return $this->json([
            'name' => 'Samvaad API',
            'version' => 'v1',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/items', 'description' => 'List all items'],
                ['method' => 'GET', 'path' => '/api/v1/items/{id}', 'description' => 'Get single item'],
                ['method' => 'POST', 'path' => '/api/v1/items', 'description' => 'Create new item'],
            ]
        ], 200);
    }
}