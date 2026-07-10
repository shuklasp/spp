<?php
namespace App\test_api_app\Serv;

/**
 * ============================================================================
 * HomeController — Blade View Rendering
 * ============================================================================
 *
 * HOW THIS WORKS:
 * Controllers are referenced in pages.yml as:
 *   home:
 *     controller: \App\test_api_app\Serv\HomeController@index
 *
 * When SPPRouter matches the route, ViewRouter calls this method.
 * The method should return rendered HTML (string).
 *
 * RENDERING BLADE VIEWS:
 * SPPBlade looks for templates in: src/test_api_app/resources/views/
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
 * HOW TO ADD A NEW PAGE:
 *   1. Create resources/views/mypage.blade.php
 *   2. Add method: public function mypage() { return $this->render('mypage'); }
 *   3. Add route in pages.yml: mypage: { controller: \App\test_api_app\Serv\HomeController@mypage }
 * ============================================================================
 */
class HomeController
{
    public function index()
    {
        // Boot SPP-UX assets so Blade views can use @sppux directive
        if (class_exists('\SPPMod\Drishyam\SPPUX')) {
            \SPPMod\Drishyam\SPPUX::boot();
        }

        return $this->render('home', [
            'title' => 'Welcome to test_api_app',
            'features' => [
                ['icon' => '🚀', 'title' => 'SPP-UX Components', 'desc' => 'Reactive UI with zero dependencies'],
                ['icon' => '🎨', 'title' => 'Blade Templates', 'desc' => 'Server-rendered with custom directives'],
                ['icon' => '⚡', 'title' => 'REST API', 'desc' => 'JSON endpoints with entity CRUD'],
                ['icon' => '🔒', 'title' => 'Authentication', 'desc' => 'Session guards with SPPAuth'],
            ]
        ]);
    }

    public function about()
    {
        return $this->render('about', [
            'title' => 'About test_api_app',
        ]);
    }

    /**
     * Guide page — renders the comprehensive Blade mode tutorial.
     * Route: guide => HomeController@guide (in pages.yml)
     */
    public function guide()
    {
        return $this->render('guide', [
            'title' => 'test_api_app Developer Guide',
        ]);
    }

    /**
     * Helper: Render a Blade template with data.
     */
    protected function render(string $view, array $data = []): string
    {
        $blade = \SPPMod\Drishyam\SPPBlade::getInstance();
        $data['app_name'] = 'test_api_app';
        $data['base_url'] = \SPP\App::getBaseUrl('test_api_app');
        return $blade->run($view, $data);
    }
}