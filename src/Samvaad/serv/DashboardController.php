<?php
namespace App\Samvaad\Serv;

/**
 * ============================================================================
 * DashboardController — Data Display with Auth & Enterprise Capabilities
 * ============================================================================
 *
 * Demonstrates:
 *   - Rendering Blade views with dynamic data
 *   - Authentication checking before rendering
 *   - Database entity queries (when SPPDB is available)
 *   - Passing data to Blade templates
 *   - Enterprise features: Request Validation, DTO Hydration, Turbo Streams & CSP Nonces
 *   - External Partials / Streams: Reference standalone .html, .php, or .js files instead of writing HTML literals
 * ============================================================================
 */
class DashboardController extends \SPPMod\SPPView\ViewController
{
    public function __construct()
    {
        // Share common dashboard metadata with all views
        $this->share('section', 'Dashboard');
    }

    public function index()
    {
        // Boot SPP-UX for @sppux directive
        if (class_exists('\SPPMod\Drishyam\SPPUX')) {
            \SPPMod\Drishyam\SPPUX::boot();
        }

        // Check authentication (optional — uncomment to require login)
        // if (class_exists('\SPPMod\SPPAuth\SPPAuth') && !\SPPMod\SPPAuth\SPPAuth::authSessionExists()) {
        //     header('Location: ' . \SPP\App::getBaseUrl('Samvaad') . '/login');
        //     exit;
        // }

        // Example: Check if request is HTMX or Turbo Streams and return external partials or stream files
        // if ($this->isHtmx()) {
        //     return $this->renderPartial('partials/dashboard_items.html', ['items' => []]);
        // }
        // if (isset($_GET['stream'])) {
        //     return $this->stream('streams/live_stats.blade.php', ['stats' => ['active' => 5]]);
        // }

        // Example: Query database entities
        $items = [];
        // Uncomment when SPPDB is configured:
        // $db = new \SPPMod\SPPDB\SPPDB();
        // $items = $db->execute_query('SELECT * FROM Samvaad_items ORDER BY id DESC LIMIT 10');

        return $this->render('dashboard', [
            'app_name' => 'Samvaad',
            'base_url' => \SPP\App::getBaseUrl('Samvaad'),
            'csp_nonce' => $this->getCspNonce(),
            'items' => $items,
            'stats' => [
                'total_items' => count($items),
                'active' => 0,
                'completed' => 0,
            ]
        ]);
    }

    /**
     * Store new dashboard item demonstrating Enterprise Validation & Hydration
     */
    public function store()
    {
        // Enterprise Validation Example
        $valResult = $this->validate([
            'title' => ['required', 'min:3'],
            'status' => ['required']
        ]);

        if (!$valResult->isValid()) {
            return $this->json(['success' => false, 'errors' => $valResult->getErrors()], 422);
        }

        // Enterprise DTO Hydration Example
        // $dto = $this->hydrate(\App\Samvaad\DTO\ItemDTO::class, $_POST);

        // Example: Return an external partial file upon successful creation (e.g. inserting new item via HTMX)
        // if ($this->isHtmx()) {
        //     return $this->renderPartial('partials/single_item.php', ['title' => $_POST['title']]);
        // }

        return $this->json(['success' => true, 'message' => 'Item stored successfully'], 201);
    }

    /**
     * Workflow Transition endpoint demonstrating Smart Content Negotiation & Core Workflow Orchestration
     */
    public function transition()
    {
        $id = $_POST['id'] ?? null;
        $transition = $_POST['transition'] ?? 'start';
        
        if (!$id || !class_exists('\\SPPMod\\SPPDB\\SPPEntity')) {
            return $this->json(['success' => false, 'error' => 'Entity system not available'], 422);
        }

        $entity = \SPPMod\SPPDB\SPPEntity::load('Samvaad_items', $id);
        if (!$entity) {
            return $this->json(['success' => false, 'error' => 'Entity not found'], 404);
        }

        // Evaluate transition using native ViewController helper with smart content negotiation
        // Serves external HTMX partial or real-time Turbo Stream automatically based on request headers
        return $this->transitionEntity($entity, $transition, ['comment' => 'Dashboard transition'], 'partials/item_row.html', 'partials/error_alert.html');
    }
}