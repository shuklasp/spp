<?php
namespace App\Samvaad\Serv;

/**
 * ============================================================================
 * HomeController — Blade View Rendering
 * ============================================================================
 *
 * HOW THIS WORKS:
 * Controllers are referenced in pages.yml as:
 *   home:
 *     controller: \App\Samvaad\Serv\HomeController@index
 *
 * When SPPRouter matches the route, ViewRouter calls this method.
 * The method should return rendered HTML (string).
 *
 * RENDERING BLADE VIEWS:
 * SPPBlade looks for templates in: src/Samvaad/resources/views/
 * Template names use dot notation: 'home' → home.blade.php
 * Layouts use @extends('layouts.app') → layouts/app.blade.php
 *
 * SPP BLADE DIRECTIVES:
 *   @sppux('component', ['prop' => 'val'])  — Mount SPP-UX component
 *   @sppform('formName')                     — Render YAML form
 *   @sppauth ... @endsppauth                — Show only if logged in
 *   @sppguest ... @endsppguest              — Show only if NOT logged in
 *   @sppbind($entity)                       — Bind entity to form
 *   @sppoffline('key') ... @endsppoffline   — Offline cache template
 *
 * ENTERPRISE VIEWCONTROLLER CAPABILITIES:
 *   - $this->share($key, $value)      : Share data across all rendered views in this controller.
 *   - $this->validate(array $rules)   : Validate incoming requests using ViewValidator rules.
 *   - $this->hydrate(string $class)   : Dynamically hydrate DTOs or Entities using DataTransformers.
 *   - $this->getCspNonce()            : Secure per-request Content Security Policy nonce generation.
 *   - $this->stream($view, $data)     : Real-time Turbo Streams / live view streaming.
 *   - $this->json($data, $status)     : Send standardized JSON responses instantly.
 *   - External Partials / Streams     : Reference standalone .html, .php, or .js files instead of writing HTML literals
 *                                       to dynamically update or insert content at particular places in the main page.
 *
 * HOW TO ADD A NEW PAGE:
 *   1. Create resources/views/mypage.blade.php
 *   2. Add method: public function mypage() { return $this->render('mypage'); }
 *   3. Add route in pages.yml: mypage: { controller: \App\Samvaad\Serv\HomeController@mypage }
 * ============================================================================
 */
class HomeController extends \SPPMod\SPPView\ViewController
{
    public function __construct()
    {
        // Example: Share common application meta across all views
        $this->share('app_title', 'Samvaad Portal');
    }

    public function index()
    {
        // Boot SPP-UX assets so Blade views can use @sppux directive
        if (class_exists('\SPPMod\Drishyam\SPPUX')) {
            \SPPMod\Drishyam\SPPUX::boot();
        }

        // Generate CSP nonce for inline scripts or styles
        $cspNonce = $this->getCspNonce();

        // Example: Return an external partial file for HTMX / Ajax requests to insert into a specific DOM location
        // if ($this->isHtmx()) {
        //     return $this->renderPartial('partials/user-card.html', ['title' => 'HTMX Partial']);
        // }

        return $this->render('home', [
            'title' => 'Welcome to Samvaad',
            'csp_nonce' => $cspNonce,
            'features' => [
                ['icon' => '🚀', 'title' => 'SPP-UX Components', 'desc' => 'Reactive UI with zero dependencies'],
                ['icon' => '🎨', 'title' => 'Blade Templates', 'desc' => 'Server-rendered with custom directives'],
                ['icon' => '⚡', 'title' => 'REST API', 'desc' => 'JSON endpoints with entity CRUD'],
                ['icon' => '🔒', 'title' => 'Authentication', 'desc' => 'Session guards with SPPAuth'],
                ['icon' => '🛡️', 'title' => 'Enterprise Architecture', 'desc' => 'Request validation, DTO hydration, CSP nonces & view streaming'],
                ['icon' => '📁', 'title' => 'External Partials', 'desc' => 'Reference standalone .html, .php, or .js files instead of writing HTML literals'],
            ]
        ]);
    }

    public function about()
    {
        return $this->render('about', [
            'title' => 'About Samvaad',
        ]);
    }

    /**
     * Guide page — renders the comprehensive Blade mode tutorial.
     * Route: guide => HomeController@guide (in pages.yml)
     */
    public function guide()
    {
        return $this->render('guide', [
            'title' => 'Samvaad Developer Guide',
        ]);
    }
}