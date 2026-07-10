<?php
namespace App\TestApp2\Serv;

use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;
use SPPMod\SPPView\Attributes\Title;

/**
 * ============================================================================
 * AttributeController — PHP 8 Attribute-Based Routing
 * ============================================================================
 *
 * HOW PHP 8 ATTRIBUTE ROUTING WORKS:
 * Instead of defining routes in pages.yml, you can use PHP 8 Attributes
 * directly on controller methods. The SPP RouteScanner automatically
 * discovers these attributes and registers routes.
 *
 * AVAILABLE ATTRIBUTES:
 *
 *   #[Route('/path', method: 'GET|POST', name: 'route.name', middleware: [])]
 *     - path:       URL path (supports {param} placeholders)
 *     - method:     HTTP methods (default: 'GET|POST')
 *     - name:       Named route for reverse routing
 *     - middleware:  Array of middleware class names
 *     - Can be applied to CLASS (prefix) or METHOD (individual route)
 *
 *   #[Middleware(ClassName::class)]
 *     - Applied to CLASS or METHOD
 *     - Class-level middleware applies to ALL methods
 *     - Method-level middleware stacks with class-level
 *     - IS_REPEATABLE: can use multiple times
 *
 *   #[Title('Page Title')]
 *     - CLASS-level only
 *     - Sets the page <title> for all routes in this controller
 *
 *   #[Lazy(action: 'load')]
 *     - CLASS-level only
 *     - Marks controller for lazy loading (deferred initialization)
 *
 *   #[Isolate]
 *     - CLASS-level only
 *     - Runs controller in an isolated context (no shared state)
 *
 * HOW ROUTES ARE DISCOVERED:
 * The SPP RouteScanner scans these directories for #[Route] attributes:
 *   - {app_dir}/controllers/
 *   - {app_dir}/src/Controllers/
 *   - {app_dir}/src/controllers/
 *
 * Routes are cached in var/cache/routes_{app}.php for performance.
 * Cache is auto-invalidated in development mode (SPP_DEBUG=true).
 *
 * WHEN TO USE ATTRIBUTES vs pages.yml:
 *   - Attributes: Best for controller-heavy apps (keeps routes with code)
 *   - pages.yml:  Best for page-file routes, assets, special pages
 *   - Both can coexist — attribute routes supplement pages.yml
 *
 * HOW TO ADD A NEW ATTRIBUTE ROUTE:
 *   1. Add #[Route('/path')] above any public method
 *   2. Clear route cache: rm var/cache/routes_TestApp2.php
 *   3. The route is automatically discovered
 * ============================================================================
 */

// Class-level #[Route] sets a prefix for all method routes
#[Route('/attr')]
#[Title('TestApp2 Attribute Routes')]
class AttributeController
{
    /**
     * GET /attr/hello
     * Basic attribute-routed endpoint.
     */
    #[Route('/hello', method: 'GET', name: 'attr.hello')]
    public function hello()
    {
        return '<div style="max-width:700px;margin:2rem auto;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid #e2e8f0;font-family:Inter,sans-serif;">
            <span style="display:inline-block;background:#dbeafe;color:#1d4ed8;padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;margin-bottom:1rem;">#[Route] ATTRIBUTE</span>
            <h1 style="margin:0 0 0.5rem;">Hello from AttributeController!</h1>
            <p style="color:#64748b;line-height:1.7;">This route was defined using a PHP 8 <code>#[Route]</code> attribute instead of pages.yml.</p>
            <pre style="background:#f1f5f9;padding:1rem;border-radius:10px;font-size:0.85rem;overflow-x:auto;">
#[Route(\'/hello\', method: \'GET\', name: \'attr.hello\')]
public function hello() { ... }</pre>
        </div>';
    }

    /**
     * GET /attr/greet/{name}
     * Parameterized route with {name} placeholder.
     */
    #[Route('/greet/{name}', method: 'GET', name: 'attr.greet')]
    public function greet()
    {
        // Route parameters are available via SPPGlobal
        $pageData = \SPPMod\SPPView\SPPGlobal::get('page');
        $name = $pageData['named_params']['name'] ?? $pageData['params'][0] ?? 'World';

        return '<div style="max-width:700px;margin:2rem auto;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid #e2e8f0;font-family:Inter,sans-serif;">
            <h1 style="margin:0 0 0.5rem;">Hello, ' . htmlspecialchars($name) . '!</h1>
            <p style="color:#64748b;">This is a parameterized route: <code>#[Route(\'/greet/{name}\')]</code></p>
            <p style="color:#64748b;">The <code>{name}</code> parameter was extracted automatically.</p>
        </div>';
    }

    /**
     * GET|POST /attr/api/data
     * JSON API endpoint with attribute routing.
     */
    #[Route('/api/data', method: 'GET|POST', name: 'attr.api.data')]
    public function apiData()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'source' => 'AttributeController',
            'method' => $_SERVER['REQUEST_METHOD'],
            'message' => 'This JSON endpoint uses #[Route] attribute routing.',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * GET /attr/protected
     * Route with method-level middleware.
     */
    #[Route('/protected', method: 'GET', name: 'attr.protected')]
    #[Middleware('\App\TestApp2\Middleware\AuthGuard')]
    public function protectedRoute()
    {
        return '<div style="max-width:700px;margin:2rem auto;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.04);border:1px solid #e2e8f0;font-family:Inter,sans-serif;">
            <span style="display:inline-block;background:#dcfce7;color:#16a34a;padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;">AUTHENTICATED</span>
            <h1 style="margin:0.5rem 0;">Protected Route</h1>
            <p style="color:#64748b;">This route uses <code>#[Middleware]</code> attribute for auth protection.</p>
            <pre style="background:#f1f5f9;padding:1rem;border-radius:10px;font-size:0.85rem;">#[Route(\'/protected\')]
#[Middleware(\'\App\TestApp2\Middleware\AuthGuard\')]
public function protectedRoute() { ... }</pre>
        </div>';
    }
}