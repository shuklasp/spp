<?php
/**
 * ============================================================================
 * SPP Framework — COMPREHENSIVE Developer Guide
 * ============================================================================
 *
 * This page is a COMPLETE tutorial for someone who has never used SPP before.
 * It covers every major feature with code examples and explanations.
 *
 * Generated for application: Samvaad
 *
 * HOW THIS PAGE WORKS:
 *   - This is a "native PHP page" rendered by the SPP view layer
 *   - It is mapped in etc/apps/Samvaad/pages.yml as:
 *       guide:
 *         url: pages/guide.php
 *   - SPP automatically injects theme CSS/JS around it (augmentation)
 *   - You can use PHP code at the top, then output HTML below
 *
 * TIP: You can delete this file once you understand the framework!
 * ============================================================================
 */

// Boot SPP-UX if available (allows mounting components on this page)
if (class_exists('\SPPMod\Drishyam\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot();
}
?>
<div style="max-width: 1100px; margin: 2rem auto; padding: 0 1rem; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
    <div style="background: #fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04); border: 1px solid #e2e8f0;">

        <span style="display:inline-block; background:#e0e7ff; color:#4f46e5; padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-bottom:1rem; letter-spacing:0.05em;">COMPREHENSIVE DEVELOPER GUIDE</span>
        <h1 style="margin: 0 0 0.5rem; font-size: 2rem; color: #1e293b;">SPP Framework — Complete Guide</h1>
        <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 2.5rem;">Application: <code style="background:#f1f5f9;padding:2px 8px;border-radius:6px;">Samvaad</code> &mdash; Everything a complete novice needs to build any workflow</p>

        <!-- ================================================================ -->
        <!-- SECTION 1: What is SPP? -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">1. What is SPP?</h2>
        <p style="color:#475569; line-height:1.8;">
            <b>SPP (Scalable PHP Platform)</b> is a full-stack PHP framework designed for building everything from simple websites to enterprise applications.
            It provides routing, templating (Blade), reactive components (SPP-UX), database ORM, authentication, testing, i18n, and much more &mdash; all in one cohesive package.
        </p>
        <p style="color:#475569; line-height:1.8;">
            <b>Key Philosophy:</b> Convention over configuration. SPP uses YAML files for forms, routes, events, and services &mdash; minimizing boilerplate PHP code.
            You write a YAML definition, and the framework auto-processes it.
        </p>
        <p style="color:#475569; line-height:1.8;">
            <b>Six App Modes:</b> <code>mixed</code> (flagship, all paradigms), <code>sppux</code> (reactive SPA), <code>blade</code> (server-rendered templates),
            <code>native</code> (raw PHP), <code>api</code> (headless REST), <code>dropin</code> (low-code HTML/PHP). Your app is: <code>Samvaad</code>.
        </p>

        <!-- ================================================================ -->
        <!-- SECTION 1A: Enterprise ViewController Capabilities -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">1A. Enterprise ViewController Capabilities</h2>
        <p style="color:#475569; line-height:1.8;">
            SPP's <code>ViewController</code> and <code>ResourceController</code> provide state-of-the-art enterprise architecture features out of the box:
        </p>
        <ul style="color:#475569; line-height:1.8; padding-left:1.5rem;">
            <li><b>Request Validation:</b> <code>$this->validate(['field' => ['required', 'min:3']])</code> uses <code>ViewValidator</code> for robust request verification.</li>
            <li><b>DTO Hydration:</b> <code>$this->hydrate(MyDTO::class, $_POST)</code> dynamically populates objects using custom <code>DataTransformer</code> instances (e.g. <code>DateTransformer</code>, <code>JsonTransformer</code>).</li>
            <li><b>CSP Nonce Management:</b> <code>$this->getCspNonce()</code> provides a secure per-request nonce for inline scripts and styles.</li>
            <li><b>Turbo Streams View Streaming:</b> <code>$this->stream('view', $data)</code> streams real-time updates and partials directly over HTTP.</li>
            <li><b>Middleware Reflection:</b> Attach <code>#[Middleware(AuthGuard::class)]</code> attributes directly to controller classes or methods.</li>
            <li><b>View Composers & Shared Data:</b> <code>$this->share($key, $value)</code> and <code>ViewComposer</code> dynamically inject shared data across rendered views.</li>
            <li><b>View Locator & Exceptions:</b> <code>ViewLocator</code> caches path discovery, throwing standardized <code>ViewNotFoundException</code> on missing templates.</li>
        </ul>

        <!-- ================================================================ -->
        <!-- SECTION 1B: Referencing External Partials & Components -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">1B. Referencing External Partials &amp; Components (Avoiding HTML Literals)</h2>
        <p style="color:#475569; line-height:1.8;">
            To maintain clean code separation and avoid writing raw HTML string literals in your controllers or JavaScript components, SPP provides built-in mechanisms to reference standalone <code>.html</code>, <code>.php</code>, and <code>.js</code> files:
        </p>
        <ul style="color:#475569; line-height:1.8; padding-left:1.5rem;">
            <li><b>Frontend SPP-UX Components:</b> Instead of inline <code>html`...`</code> template literals, use <code>this.service()</code> to fetch server-rendered partials or <code>fetch()</code> static <code>.html</code> files, injecting them into the DOM via <code>new TrustedHTML(templateHtml)</code>.</li>
            <li><b>HTMX &amp; AJAX Partials:</b> In controllers, return <code>$this->renderPartial('partials/table-rows.html', $data)</code> to let <code>ViewLocator</code> automatically discover the external file and render it without layouts for precise DOM swapping.</li>
            <li><b>Live Turbo Streams:</b> Call <code>$this->stream('streams/live-update.blade.php', $data)</code> referencing an external stream view file instead of constructing raw XML/HTML string literals in PHP.</li>
            <li><b>Main Page Composition:</b> Assemble main pages cleanly using <code>@include('partials.header')</code>, <code>@sppux('my-widget')</code>, <code>ViewComposer</code> bindings, or native <code>$this->renderView('partials/widget', $data)</code>.</li>
        </ul>

        <!-- ================================================================ -->
        <!-- SECTION 2: App Structure -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">2. App Structure</h2>
        <p style="color:#475569; line-height:1.8;">Every SPP app has two main directories: <code>etc/apps/Samvaad/</code> for configuration, and <code>src/Samvaad/</code> for source code.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
your-project/
├── etc/apps/Samvaad/         # ── CONFIGURATION ──
│   ├── pages.yml                  # Route definitions (URL → controller/page)
│   ├── forms/                     # YAML form definitions
│   │   └── contact.yml            # Contact form fields + validation
│   ├── events.yml                 # Event listener registrations
│   ├── services.yml               # PHP↔JS bridge service definitions
│   ├── middleware.yml              # Middleware pipeline configuration
│   └── modules.yml                # Module loading configuration
│
├── src/Samvaad/              # ── SOURCE CODE ──
│   ├── init.php                   # App bootstrap (runs on every request)
│   ├── index.php                  # SPP-UX SPA entry point
│   ├── pages/                     # Native PHP page files
│   │   ├── contact.php            # Contact form page
│   │   ├── guide.php              # This guide page
│   │   └── errors/                # Custom error pages
│   │       ├── 404.php            # Page Not Found
│   │       └── 500.php            # Internal Server Error
│   ├── serv/                      # Controllers (Blade or JSON)
│   │   ├── HomeController.php     # Home + About pages
│   │   ├── DashboardController.php
│   │   ├── ApiController.php      # REST API endpoints
│   │   └── AuthController.php     # Login/Logout
│   ├── comp/                      # SPP-UX components (JS)
│   │   ├── hello-world.js         # Sample reactive component
│   │   └── task-manager.js        # CRUD component example
│   ├── entities/                  # Database entity definitions
│   ├── middleware/                 # Custom middleware classes
│   ├── events/                    # Event handler classes
│   ├── etc/                       # App-level config overrides
│   └── tests/                     # Parikshak test files
│
├── resources/Samvaad/
│   └── views/                     # Blade template files (.blade.php)
│       ├── layout.blade.php       # Master layout
│       ├── home.blade.php         # Home view
│       └── dashboard.blade.php    # Dashboard view
│
└── spp/                           # Framework core (do not modify)
    ├── spp.php                    # CLI entry point
    └── etc/global-settings.yml    # All apps registered here</pre>

        <!-- ================================================================ -->
        <!-- SECTION 3: Routing -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">3. Routing</h2>
        <p style="color:#475569; line-height:1.8;">Routes are defined in <code>etc/apps/Samvaad/pages.yml</code>. SPP supports five route types:</p>

        <h3 style="color:#475569; margin-top:1.5rem;">3a. pages.yml Route Types</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── etc/apps/Samvaad/pages.yml ──

defaults:
  home: home                              # Default page when no route matches
  pagedir: /src/Samvaad              # Base directory for page files

pages:
  # TYPE 1: Controller route — calls a PHP class method
  home:
    controller: \App\Samvaad\Serv\HomeController@index

  # TYPE 2: Page file route — includes a PHP file (with theme augmentation)
  contact:
    url: pages/contact.php

  # TYPE 3: Special route — standalone HTML (NO theme augmentation)
  app:
    url: index.php
    special: 1

  # TYPE 4: Asset route — serves static files from a directory
  assets:
    assets: assets

  # TYPE 5: Parameterized route — dynamic URL segments
  api/v1/items/{id}:
    controller: \App\Samvaad\Serv\ApiController@show</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">3b. PHP 8 Attribute Routing</h3>
        <p style="color:#475569; line-height:1.8;">Instead of YAML, you can use PHP 8 Attributes directly on controller methods. The <code>RouteScanner</code> discovers them automatically.</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.7rem;">Attribute</th><th style="text-align:left; padding:0.7rem;">Target</th><th style="text-align:left; padding:0.7rem;">Description</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>#[Route('/path', method, name, middleware)]</code></td><td style="padding:0.7rem;">Class / Method</td><td style="padding:0.7rem;">Define URL. Class-level = prefix for all methods.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>#[Middleware(Class::class)]</code></td><td style="padding:0.7rem;">Class / Method</td><td style="padding:0.7rem;">Attach middleware. Repeatable. Stacks with parent.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>#[Title('Page Title')]</code></td><td style="padding:0.7rem;">Class</td><td style="padding:0.7rem;">Sets &lt;title&gt; tag for the page.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>#[Lazy(action: 'load')]</code></td><td style="padding:0.7rem;">Class</td><td style="padding:0.7rem;">Deferred initialization — load only when needed.</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>#[Isolate]</code></td><td style="padding:0.7rem;">Class</td><td style="padding:0.7rem;">Isolated context (no shared state with other controllers).</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>#[DataProvider('method')]</code></td><td style="padding:0.7rem;">Method</td><td style="padding:0.7rem;">Data-driven Parikshak tests.</td></tr>
        </table>

        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\Attributes\Middleware;

#[Route('/admin')]                 // prefix for all methods in this class
#[Middleware(AuthGuard::class)]    // applied to ALL methods
class AdminController extends \SPPMod\SPPView\ViewController
{
    #[Route('/dashboard', method: 'GET', name: 'admin.dashboard')]
    public function dashboard() { /* handles /admin/dashboard */ }

    #[Route('/users/{id}', method: 'GET')]
    public function showUser() { /* handles /admin/users/42 */ }

    #[Route('/settings', method: 'POST')]
    #[Middleware(CsrfGuard::class)]  // method-level stacks with class-level
    public function saveSettings() { /* handles POST /admin/settings */ }
}

// Discovery paths: controllers/, src/Controllers/
// Cache file: var/cache/routes_Samvaad.php</pre>

        <!-- ================================================================ -->
        <!-- SECTION 4: Controllers -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">4. Controllers</h2>
        <p style="color:#475569; line-height:1.8;">Controllers live in <code>src/Samvaad/serv/</code>. They handle requests and return either rendered Blade HTML or JSON data.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── src/Samvaad/serv/HomeController.php ──

namespace App\Samvaad\Serv;

class HomeController extends \SPPMod\SPPView\ViewController
{
    // RENDERING A VIEW:
    // $this->render('viewName', $data) renders resources/Samvaad/views/viewName.blade.php
    // Extensible via events (e.g. SPPBlade injects its rendering logic automatically)
    public function index()
    {
        return $this-&gt;render('home', [
            'title'   =&gt; 'Welcome to Samvaad',
            'message' =&gt; 'Hello from your controller!',
        ]);
    }

    // RETURNING JSON (for API endpoints):
    public function apiEndpoint()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' =&gt; 'success',
            'data'   =&gt; ['item1', 'item2'],
        ]);
    }

    // REDIRECTING:
    public function saveAndRedirect()
    {
        // ... save data ...
        header('Location: /Samvaad/home');
        exit;
    }
}</pre>

        <!-- ================================================================ -->
        <!-- SECTION 5: Blade Templates -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">5. Blade Templates</h2>
        <p style="color:#475569; line-height:1.8;">Blade is the template engine (from Laravel, extended by SPP). Templates live in <code>resources/Samvaad/views/</code>.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">5a. Layout System</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- ── resources/Samvaad/views/layout.blade.php ── --}}
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;@yield('title', 'My App')&lt;/title&gt;  {{-- Default title if not set --}}
&lt;/head&gt;
&lt;body&gt;
    @yield('content')    {{-- Child views inject content here --}}
&lt;/body&gt;
&lt;/html&gt;

{{-- ── resources/Samvaad/views/home.blade.php ── --}}
@extends('layout')               {{-- Use layout.blade.php as parent --}}

@section('title', 'Home Page')   {{-- Override the title --}}

@section('content')               {{-- Fill the content slot --}}
    &lt;h1&gt;Welcome, {{ $name }}&lt;/h1&gt;  {{-- Echo escaped variable --}}
    {!! $rawHtml !!}               {{-- Echo RAW HTML (be careful!) --}}
@endsection</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">5b. SPP Custom Blade Directives</h3>
        <p style="color:#475569; line-height:1.8;">SPP extends Blade with powerful custom directives. Here is the <b>complete list</b>:</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.7rem;">Directive</th><th style="text-align:left; padding:0.7rem;">Purpose</th><th style="text-align:left; padding:0.7rem;">Example</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppux('component', [...])</code></td><td style="padding:0.7rem;">Mount an SPP-UX reactive component</td><td style="padding:0.7rem;"><code>@sppux('task-manager', ['user' =&gt; $id])</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppform('form_name')</code></td><td style="padding:0.7rem;">Render a complete YAML-defined form</td><td style="padding:0.7rem;"><code>@sppform('contact')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppform_start('form')</code> / <code>@sppform_end</code></td><td style="padding:0.7rem;">Manual form rendering (custom layout)</td><td style="padding:0.7rem;"><code>@sppform_start('contact') ... @sppform_end</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppelement('element')</code></td><td style="padding:0.7rem;">Render a reusable UI element</td><td style="padding:0.7rem;"><code>@sppelement('navbar')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppauth</code> / <code>@endsppauth</code></td><td style="padding:0.7rem;">Show content only to logged-in users</td><td style="padding:0.7rem;"><code>@sppauth &lt;p&gt;Welcome back!&lt;/p&gt; @endsppauth</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppguest</code> / <code>@endsppguest</code></td><td style="padding:0.7rem;">Show content only to guests (not logged in)</td><td style="padding:0.7rem;"><code>@sppguest &lt;a href="/login"&gt;Login&lt;/a&gt; @endsppguest</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@sppbind('var')</code></td><td style="padding:0.7rem;">Two-way reactive data binding</td><td style="padding:0.7rem;"><code>&lt;input @sppbind('username')&gt;</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@react('Component')</code></td><td style="padding:0.7rem;">Mount a React component</td><td style="padding:0.7rem;"><code>@react('MyReactApp')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@vue('Component')</code></td><td style="padding:0.7rem;">Mount a Vue component</td><td style="padding:0.7rem;"><code>@vue('MyVueWidget')</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.7rem;"><code>@sppoffline</code> / <code>@endsppoffline</code></td><td style="padding:0.7rem;">Offline-capable content (service worker)</td><td style="padding:0.7rem;"><code>@sppoffline Cached content @endsppoffline</code></td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.7rem;"><code>@module_component('mod', 'comp')</code></td><td style="padding:0.7rem;">Render a component from a module</td><td style="padding:0.7rem;"><code>@module_component('sppauth', 'login-form')</code></td></tr>
        </table>

        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
{{-- EXAMPLE: Using multiple directives in one view --}}
@extends('layout')

@section('content')
    @sppauth
        &lt;h1&gt;Welcome back, {{ $user-&gt;name }}!&lt;/h1&gt;
        @sppux('task-manager', ['userId' =&gt; $user-&gt;id])
        @sppform('feedback')
    @endsppauth

    @sppguest
        &lt;h1&gt;Please log in&lt;/h1&gt;
        @module_component('sppauth', 'login-form')
    @endsppguest

    @sppoffline
        &lt;p&gt;You appear to be offline. Cached content is shown.&lt;/p&gt;
    @endsppoffline
@endsection</pre>

        <!-- ================================================================ -->
        <!-- SECTION 6: SPP-UX Components -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">6. SPP-UX Components</h2>
        <p style="color:#475569; line-height:1.8;">SPP-UX is the reactive component system. Components are JavaScript classes in <code>src/Samvaad/comp/</code>.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── comp/my-counter.js ──
export default class MyCounter extends BaseComponent {
    // LIFECYCLE: Called once when component initializes
    async onInit() {
        this.setState({ count: 0, label: 'Click me' });
    }

    // LIFECYCLE: Called after DOM is ready
    async onMount() {
        console.log('Counter mounted!');
    }

    // LIFECYCLE: Called before component is removed
    async onDestroy() {
        console.log('Cleanup here');
    }

    // RENDER: Return HTML template using tagged template literals
    render() {
        return html`
            &lt;button @click="${() =&gt; this.increment()}"
                    style="padding:1rem 2rem; background:#6366f1; color:#fff;
                           border:none; border-radius:10px; cursor:pointer;"&gt;
                ${this.state.label}: ${this.state.count}
            &lt;/button&gt;`;
    }

    increment() {
        this.setState({ count: this.state.count + 1 });
    }
}

// ── MOUNTING COMPONENTS (3 ways) ──
// 1. In PHP:   \SPPMod\Drishyam\SPPUX::render('my-counter')
// 2. In Blade: @sppux('my-counter', ['label' =&gt; 'Clicks'])
// 3. In HTML:  &lt;div data-spp-component="my-counter"&gt;&lt;/div&gt;</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">Stores (Shared State)</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Stores allow multiple components to share state
const store = this.getStore('appStore');
store.set('user', { name: 'John' });
const user = store.get('user');

// Subscribe to changes:
store.subscribe('user', (newVal) =&gt; {
    console.log('User changed:', newVal);
});</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">Theme Picker Component</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Built-in theme picker component for switching themes
// Mount via: @sppux('theme-picker') or SPPUX::render('theme-picker')
// Themes defined in resources/Samvaad/themes/ with theme.yml</pre>

        <!-- ================================================================ -->
        <!-- SECTION 7: Events System -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">7. Events System</h2>
        <p style="color:#475569; line-height:1.8;">Events decouple your code. Register listeners in <code>init.php</code> or <code>etc/events.yml</code>, then fire them anywhere.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">7a. Registering &amp; Firing Events</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── In init.php (imperative) ──
// Listen to an existing event:
\SPP\SPPEvent::listen('PageNotFound', [new \App\Samvaad\Events\ErrorHandler(), 'onNotFound']);
\SPP\SPPEvent::listen('AppBoot', function($params) {
    // Run code when app boots
});

// Fire a custom event:
\SPP\SPPEvent::fireEvent('app.itemCreated', new \SPP\EventParams([
    'item_id' =&gt; 42,
    'user'    =&gt; $currentUser,
]));

// ── In etc/events.yml (declarative) ──
events:
  - event: "AppBoot"
    handler: "App\Samvaad\Events\AppBootHandler::onBoot"
  - event: "PageNotFound"
    handler: "App\Samvaad\Events\ErrorHandler::onNotFound"
  - event: "core.error.exception"
    handler: "App\Samvaad\Events\ErrorHandler::onException"</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">7b. All Framework Events</h3>
        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.6rem;">Event Name</th><th style="text-align:left; padding:0.6rem;">When It Fires</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>AppBoot</code></td><td style="padding:0.6rem;">Application starts up</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>BeforeRender / AfterRender</code></td><td style="padding:0.6rem;">Page render lifecycle</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>PageNotFound</code></td><td style="padding:0.6rem;">404 — no matching route found</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>core.error.exception</code></td><td style="padding:0.6rem;">Uncaught exception occurs</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>AuthLogin / AuthLogout</code></td><td style="padding:0.6rem;">User login / logout</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>EntityCreated / Updated / Deleted</code></td><td style="padding:0.6rem;">Database entity changes</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>event_spp_view_render_theme</code></td><td style="padding:0.6rem;">Before theme rendering (inject HTML)</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><code>event_spp_page_render</code></td><td style="padding:0.6rem;">During page rendering</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><code>parikshak.suite_started / completed</code></td><td style="padding:0.6rem;">Test suite lifecycle</td></tr>
        </table>

        <!-- ================================================================ -->
        <!-- SECTION 8: Services (PHP↔JS Bridge) -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">8. Services (PHP&harr;JS Bridge)</h2>
        <p style="color:#475569; line-height:1.8;">Services let JavaScript call PHP functions without writing API endpoints. Define in <code>etc/services.yml</code>.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── etc/apps/Samvaad/services.yml ──
services:
  - name: "task.create"
    script: "src/Samvaad/serv/task_create.php"
  - name: "task.list"
    script: "src/Samvaad/serv/task_list.php"

// ── JavaScript (frontend) ──
const result = await spp_admin.callAppService('task.create', {
    title: 'New Task',
    priority: 'high'
});
console.log(result); // PHP response

// ── PHP (src/Samvaad/serv/task_create.php) ──
$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? '';
// ... process and return JSON ...
echo json_encode(['status' =&gt; 'created', 'id' =&gt; 1]);</pre>

        <!-- ================================================================ -->
        <!-- SECTION 9: Error Handling -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">9. Error Handling</h2>
        <p style="color:#475569; line-height:1.8;">SPP has a layered error handling system that works differently in debug vs production mode.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">9a. How It Works</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// SPP Error Handling Architecture:
//
// 1. SPPErrorHandler::register() — Called in bootstrap, sets up error/exception handlers
// 2. SPPError::exceptionHandler() — Catches all uncaught exceptions
// 3. SPPError::setCustomErrorHandler() — Override the default error display
//
// DEBUG MODE (SPP_DEBUG = true):
//   → Shows Ignition-style error pages with stack traces, code snippets,
//     and debugging info (like Laravel's Whoops page)
//
// PRODUCTION MODE (SPP_DEBUG = false):
//   → Shows user-friendly error pages (your custom 404.php / 500.php)
//   → Logs errors to var/log/
//
// API ERRORS:
//   → When URL starts with /api/, errors auto-return JSON:
//     {"error": "Not Found", "status": 404}

// ── Custom error handlers ──
SPPError::setCustomErrorHandler(function($error) {
    // Handle errors your way
    log_error($error);
    include 'path/to/custom-error.php';
});

// ── Triggering errors manually ──
SPPError::triggerUserError('Something went wrong');   // User-facing
SPPError::triggerDevError('Debug info here');          // Developer-facing
SPPError::triggerAdminError('Admin notification');     // Admin-facing</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">9b. Custom 404 &amp; 500 Pages</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// In init.php, redirect to your custom error pages:
\SPP\SPPEvent::listen('PageNotFound', function($params) {
    include __DIR__ . '/pages/errors/404.php';
    exit;
});

\SPP\SPPEvent::listen('core.error.exception', function($params) {
    if (!defined('SPP_DEBUG') || !SPP_DEBUG) {
        include __DIR__ . '/pages/errors/500.php';
        exit;
    }
});

// Try/catch in controllers:
try {
    $result = riskyOperation();
} catch (\Exception $e) {
    \SPP\Log::error('Operation failed', ['exception' =&gt; $e-&gt;getMessage()]);
    return $blade-&gt;run('error', ['message' =&gt; 'Something went wrong']);
}</pre>

        <!-- ================================================================ -->
        <!-- SECTION 10: Middleware -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">10. Middleware</h2>
        <p style="color:#475569; line-height:1.8;">Middleware runs before/after a request reaches your controller. Use for auth checks, logging, CORS, etc.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── src/Samvaad/middleware/AuthGuard.php ──
namespace App\Samvaad\Middleware;

class AuthGuard
{
    // The handle() method receives $request and $next callback
    public function handle($request, $next)
    {
        if (!\SPPMod\SPPAuth\SPPAuth::isLoggedIn()) {
            header('Location: /Samvaad/login');
            exit;
        }
        return $next($request); // Continue to controller
    }
}

// ── etc/apps/Samvaad/middleware.yml (global pipeline) ──
middleware:
  global:
    - \App\Samvaad\Middleware\CorsMiddleware
    - \App\Samvaad\Middleware\SessionMiddleware
  groups:
    auth:
      - \App\Samvaad\Middleware\AuthGuard
    api:
      - \App\Samvaad\Middleware\ApiRateLimit

// Per-route middleware (in pages.yml):
// dashboard:
//   controller: \App\Samvaad\Serv\DashboardController@index
//   middleware: [auth]

// Per-route via attribute:
// #[Middleware(AuthGuard::class)]
// public function dashboard() { ... }

// MiddlewareKernel processes the pipeline in order.
// Each middleware calls $next() to pass to the next one.</pre>

        <!-- ================================================================ -->
        <!-- SECTION 11: Database & Entities -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">11. Database &amp; Entities</h2>
        <p style="color:#475569; line-height:1.8;">SPP uses the <code>SPPDB</code> module for database operations and entities for ORM-style data modeling.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── Creating an Entity ──
// Run: php spp.php make:entity --app=Samvaad Task
// This creates src/Samvaad/entities/Task.php

namespace App\Samvaad\Entities;

class Task extends \SPPMod\SPPDB\Entity
{
    protected string $table = 'Samvaad_tasks';  // Uses table prefix
    protected bool $apiExpose = true;                // Auto-generates REST API

    // Define fillable fields
    protected array $fillable = ['title', 'description', 'status', 'due_date'];

    // Relationships
    public function user() {
        return $this-&gt;belongsTo(User::class, 'user_id');
    }
}

// ── Using SPPDB directly ──
$db = \SPPMod\SPPDB\SPPDB::getInstance();

// Query builder:
$tasks = $db-&gt;table('tasks')-&gt;where('status', 'active')-&gt;get();
$task  = $db-&gt;table('tasks')-&gt;find(42);

// Raw query:
$results = $db-&gt;execute_query("SELECT * FROM tasks WHERE status = ?", ['active']);

// Insert:
$db-&gt;table('tasks')-&gt;insert(['title' =&gt; 'New Task', 'status' =&gt; 'pending']);

// Update:
$db-&gt;table('tasks')-&gt;where('id', 42)-&gt;update(['status' =&gt; 'done']);

// ── Migrations ──
// php spp.php migrate --app=Samvaad
// php spp.php migrate:rollback --app=Samvaad
// php spp.php db:seed --app=Samvaad</pre>

        <!-- ================================================================ -->
        <!-- SECTION 12: YAML Forms -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">12. YAML Forms</h2>
        <p style="color:#475569; line-height:1.8;">Define forms in YAML, and SPP handles rendering, validation, and processing automatically.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── etc/apps/Samvaad/forms/contact.yml ──
id: contact
public_name: "Contact Form"
success_message: "Thank you! Your message has been received."
redirect_to: "contact"

fields:
  guest_name:
    label: "Your Name"
    type: text                 # Types: text, email, textarea, select,
    required: true             #   checkbox, radio, hidden, number, file
    validation: "min:2"        # Validation: required, min:N, max:N,
    placeholder: "John Doe"   #   email, numeric, regex:pattern
  email:
    label: "Email Address"
    type: email
    required: false
  message:
    label: "Message"
    type: textarea
    required: true
    validation: "min:10"

# ── Rendering in Blade ──
# @sppform('contact')                       — Auto-render entire form
# @sppform_start('contact') ... @sppform_end — Manual layout control

# ── Rendering in PHP ──
# &lt;form method="POST"&gt;
#   &lt;input type="hidden" name="spp_form_id" value="contact"&gt;
#   &lt;!-- your fields here --&gt;
# &lt;/form&gt;
# SPP detects spp_form_id and auto-validates/processes the POST</pre>

        <!-- ================================================================ -->
        <!-- SECTION 13: Authentication -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">13. Authentication</h2>
        <p style="color:#475569; line-height:1.8;">The <code>sppauth</code> module provides login, logout, role-based access, and session management.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPPMod\SPPAuth\SPPAuth;

// ── Login ──
$result = SPPAuth::login($username, $password);
if ($result['success']) {
    header('Location: /Samvaad/dashboard');
}

// ── Check auth status ──
if (SPPAuth::isLoggedIn()) {
    $user = SPPAuth::getUser();
    echo "Hello, " . $user['name'];
}

// ── Role-based access ──
if (SPPAuth::hasRole('admin')) {
    // Show admin content
}
SPPAuth::requireRole('editor'); // Throws exception if not editor

// ── Logout ──
SPPAuth::logout();

// ── In Blade templates ──
// @sppauth
//     Welcome, {{ SPPAuth::getUser()['name'] }}!
//     @if(SPPAuth::hasRole('admin'))
//         &lt;a href="/admin"&gt;Admin Panel&lt;/a&gt;
//     @endif
// @endsppauth
// @sppguest
//     &lt;a href="/login"&gt;Please log in&lt;/a&gt;
// @endsppguest</pre>

        <!-- ================================================================ -->
        <!-- SECTION 14: Sessions -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">14. Sessions</h2>
        <p style="color:#475569; line-height:1.8;">SPP provides a session wrapper for storing per-user data across requests.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\SPPSession;

// Set a session variable:
SPPSession::setVar('cart_items', [1, 2, 3]);
SPPSession::setVar('locale', 'en');

// Get a session variable:
$items = SPPSession::getVar('cart_items');   // [1, 2, 3]
$lang  = SPPSession::getVar('locale');       // 'en'

// Destroy a specific variable:
SPPSession::destroyVar('cart_items');

// Flash data (available for ONE request only):
SPPSession::flash('success', 'Item saved!');
$msg = SPPSession::getFlash('success'); // Available once, then auto-deleted

// Enterprise mode uses Redis for sessions (configured in global-settings.yml):
//   session_handler: redis
//   session_save_path: tcp://127.0.0.1:6379</pre>

        <!-- ================================================================ -->
        <!-- SECTION 15: Translations (i18n) -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">15. Translations (i18n)</h2>
        <p style="color:#475569; line-height:1.8;">Multi-language support via JSON language files and the <code>spplang</code> module.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── Language files: resources/Samvaad/lang/en.json ──
{
    "welcome": "Welcome to our app!",
    "greeting": "Hello, :name!",
    "items_count": "You have :count item(s)"
}

// ── resources/Samvaad/lang/es.json ──
{
    "welcome": "&iexcl;Bienvenido a nuestra app!",
    "greeting": "&iexcl;Hola, :name!"
}

// ── PHP usage ──
use SPPMod\SPPLang\Translation;

Translation::load('en');                         // Load English
echo Translation::get('welcome');                // "Welcome to our app!"
echo Translation::get('greeting', ['name' =&gt; 'John']); // "Hello, John!"

Translation::setLocale('es');                    // Switch to Spanish
echo Translation::get('welcome');                // "&iexcl;Bienvenido..."

// ── Blade usage ──
// @lang('welcome')
// @lang('greeting', ['name' =&gt; $user-&gt;name])</pre>

        <!-- ================================================================ -->
        <!-- SECTION 16: Dependency Injection -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">16. Dependency Injection</h2>
        <p style="color:#475569; line-height:1.8;">SPP includes a DI container for managing class dependencies and singletons.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\Container;
use SPP\Registry;

// ── Binding services ──
Container::bind('mailer', function() {
    return new \App\Samvaad\Services\MailService();
});

// ── Singleton (same instance every time) ──
Container::singleton('cache', function() {
    return new \App\Samvaad\Services\CacheService();
});

// ── Resolving ──
$mailer = Container::get('mailer'); // New instance each time
$cache  = Container::get('cache'); // Same instance always

// ── Registry (global key-value store) ──
Registry::set('app.version', '1.0.0');
$version = Registry::get('app.version');  // '1.0.0'</pre>

        <!-- ================================================================ -->
        <!-- SECTION 17: Polyglot Bridge -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">17. Polyglot Bridge</h2>
        <p style="color:#475569; line-height:1.8;">Execute Python, Perl, or C++ code from PHP. Great for ML models, data processing, or legacy integration.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\Polyglot;

// ── Run a Python script ──
$result = Polyglot::python('scripts/analyze.py', [
    'input_file' =&gt; '/path/to/data.csv',
    'model'      =&gt; 'sentiment',
]);

// ── Run a Perl script ──
$result = Polyglot::perl('scripts/parser.pl', ['--format=json']);

// ── Run a compiled C++ binary ──
$result = Polyglot::exec('bin/fast-compute', ['--threads=4']);

// $result contains: stdout, stderr, exit_code</pre>

        <!-- ================================================================ -->
        <!-- SECTION 18: Parikshak Testing -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">18. Parikshak Testing &#129514;</h2>
        <p style="color:#475569; line-height:1.8;">SPP's testing framework supports class-based, DSL, and evolutionary testing approaches.</p>

        <h3 style="color:#475569; margin-top:1.5rem;">18a. Class-based (SPPTestCase)</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPPMod\Parikshak\SPPTestCase;
use SPPMod\Parikshak\Attributes\DataProvider;

class TaskTest extends SPPTestCase {
    use \SPPMod\Parikshak\InteractsWithApi;    // $this-&gt;get(), post(), put(), delete()
    use \SPPMod\Parikshak\RefreshDatabase;     // Clean DB per test

    public function testTaskCreation(): void {
        $this-&gt;assertTrue($task-&gt;exists());
        $this-&gt;assertEquals('New Task', $task-&gt;title);
        $this-&gt;assertSame(42, $task-&gt;id);         // Strict ===
        $this-&gt;assertInstanceOf(Task::class, $task);
        $this-&gt;expectException(\Exception::class, fn() =&gt; $task-&gt;delete());
    }

    #[DataProvider('statusData')]    // PHP 8 attribute for data-driven tests
    public function testStatus(string $input, bool $expected): void {
        $this-&gt;assertEquals($expected, Task::isValidStatus($input));
    }

    public function statusData(): array {
        return [['active', true], ['pending', true], ['invalid', false]];
    }
}
// File naming: test.TaskTest.php in src/Samvaad/tests/</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">18b. DSL Tests (BDD-style)</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
test('task can be created', function () {
    $task = new Task(['title' =&gt; 'Test']);
    expect($task-&gt;title)-&gt;toBe('Test');
});

it('validates required fields', function () {
    expect(fn() =&gt; new Task([]))-&gt;toThrow(\Exception::class);
});

// Also: -&gt;toBeTrue(), -&gt;toBeFalse(), -&gt;toBeNull(), -&gt;toContain()</pre>

        <h3 style="color:#475569; margin-top:1.5rem;">18c. Running Tests</h3>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
php spp.php test:run --app=Samvaad               # Run full test suite
php spp.php test:run --app=Samvaad --coverage    # With code coverage report
php spp.php test:run --app=Samvaad TaskTest      # Run single test class
php spp.php test:blueprint --app=Samvaad          # Auto-generate test stubs
php spp.php test:monkey --app=Samvaad             # Fuzz/monkey testing
php spp.php test:evolve --app=Samvaad             # Evolutionary testing</pre>

        <!-- ================================================================ -->
        <!-- SECTION 19: Themes -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">19. Themes</h2>
        <p style="color:#475569; line-height:1.8;">Themes are defined in <code>resources/Samvaad/themes/</code> with a <code>theme.yml</code> config.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── resources/Samvaad/themes/default/theme.yml ──
name: "Default Theme"
version: "1.0"
css:
  - css/main.css              # Theme CSS files
  - css/components.css
js:
  - js/theme.js               # Theme JavaScript
variables:                     # CSS custom properties
  --primary-color: "#6366f1"
  --bg-color: "#ffffff"
  --text-color: "#1e293b"

# Switching themes programmatically:
# \SPP\Theme::setActive('dark');
# \SPP\Theme::getActive(); // 'dark'

# In SPP-UX: Use the theme-picker component
# @sppux('theme-picker')</pre>

        <!-- ================================================================ -->
        <!-- SECTION 20: Core Modules Inventory -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">20. Core Modules Inventory</h2>
        <p style="color:#475569; line-height:1.8;">Load modules with <code>\SPP\Module::loadModule('name')</code> or list them in <code>modules.yml</code>.</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.78rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="padding:0.5rem;">#</th><th style="padding:0.5rem;">Module</th><th style="padding:0.5rem;">Purpose</th><th style="padding:0.5rem;">Key API</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">1</td><td style="padding:0.5rem;"><b>sppview</b></td><td style="padding:0.5rem;">View layer, page rendering, theme injection</td><td style="padding:0.5rem;">SPPGlobal, ViewPage, RouteScanner</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">2</td><td style="padding:0.5rem;"><b>spprouter</b></td><td style="padding:0.5rem;">URL routing, dynamic params, named routes</td><td style="padding:0.5rem;">SPPRouter::resolve(), pages.yml</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">3</td><td style="padding:0.5rem;"><b>drishyam</b></td><td style="padding:0.5rem;">Blade templates, SPP-UX components &amp; Extensions</td><td style="padding:0.5rem;">SPPBlade, SPPUX, Sppext</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">4</td><td style="padding:0.5rem;"><b>sppdb</b></td><td style="padding:0.5rem;">Database (MySQL/PostgreSQL/SQLite)</td><td style="padding:0.5rem;">SPPDB, execute_query(), migrations</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">5</td><td style="padding:0.5rem;"><b>sppauth</b></td><td style="padding:0.5rem;">Authentication, sessions, RBAC</td><td style="padding:0.5rem;">SPPAuth::login(), isLoggedIn()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">6</td><td style="padding:0.5rem;"><b>sppapi</b></td><td style="padding:0.5rem;">REST API auto-generation from entities</td><td style="padding:0.5rem;">Entity $apiExpose = true</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">7</td><td style="padding:0.5rem;"><b>parikshak</b></td><td style="padding:0.5rem;">Testing: unit, fuzzing, evolutionary, DSL</td><td style="padding:0.5rem;">SPPTestCase, test(), expect()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">8</td><td style="padding:0.5rem;"><b>spplogger</b></td><td style="padding:0.5rem;">Structured logging, log rotation</td><td style="padding:0.5rem;">\SPP\Log::info(), error()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">9</td><td style="padding:0.5rem;"><b>sppcache</b></td><td style="padding:0.5rem;">File &amp; memory caching</td><td style="padding:0.5rem;">\SPP\Cache::get(), put()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">10</td><td style="padding:0.5rem;"><b>sppqueue</b></td><td style="padding:0.5rem;">Background jobs, scheduled tasks</td><td style="padding:0.5rem;">\SPP\Queue::dispatch()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">11</td><td style="padding:0.5rem;"><b>sppcore (Storage)</b></td><td style="padding:0.5rem;">File storage, uploads</td><td style="padding:0.5rem;">\SPP\Storage::put(), get()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">12</td><td style="padding:0.5rem;"><b>sppaudit</b></td><td style="padding:0.5rem;">Audit trail, change tracking</td><td style="padding:0.5rem;">Auto per entity</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">13</td><td style="padding:0.5rem;"><b>sppsecurity</b></td><td style="padding:0.5rem;"><i>(Merged into sppcore)</i></td><td style="padding:0.5rem;">\SPP\Core\Security\SPPSecurityService</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">14</td><td style="padding:0.5rem;"><b>sppmaker</b></td><td style="padding:0.5rem;">Code generators (entity, controller, etc)</td><td style="padding:0.5rem;">php spp.php make:entity</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">15</td><td style="padding:0.5rem;"><b>spplang</b></td><td style="padding:0.5rem;">i18n, translations, locale</td><td style="padding:0.5rem;">\SPP\Lang::get(), @lang()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">16</td><td style="padding:0.5rem;"><b>sppai</b></td><td style="padding:0.5rem;">AI/LLM integration, embeddings</td><td style="padding:0.5rem;">\SPP\AI::prompt()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">17</td><td style="padding:0.5rem;"><b>sppworkflow</b></td><td style="padding:0.5rem;">State machines, approval flows</td><td style="padding:0.5rem;">YAML state definitions</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">18</td><td style="padding:0.5rem;"><b>spplive</b></td><td style="padding:0.5rem;">Real-time updates, SSE, WebSocket</td><td style="padding:0.5rem;">\SPP\Live::broadcast()</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">19</td><td style="padding:0.5rem;"><b>sppreport</b></td><td style="padding:0.5rem;">PDF/Excel/CSV report generation</td><td style="padding:0.5rem;">Admin Reports panel</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">20</td><td style="padding:0.5rem;"><b>sppxdb</b></td><td style="padding:0.5rem;">Cross-database, federated queries</td><td style="padding:0.5rem;">Admin XDB panel</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">21</td><td style="padding:0.5rem;"><b>sppdeploy</b></td><td style="padding:0.5rem;">Deployment, migrations, env</td><td style="padding:0.5rem;">php spp.php deploy</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">22</td><td style="padding:0.5rem;"><b>sppdoc/sppdocs</b></td><td style="padding:0.5rem;">Auto API documentation</td><td style="padding:0.5rem;">Admin Docs panel</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">23</td><td style="padding:0.5rem;"><b>sppenv</b></td><td style="padding:0.5rem;">.env file support</td><td style="padding:0.5rem;">\SPP\Env::get('KEY')</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.5rem;">24</td><td style="padding:0.5rem;"><b>sppext</b></td><td style="padding:0.5rem;"><i>(Integrated into drishyam)</i></td><td style="padding:0.5rem;">\SPPMod\Drishyam\Sppext</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.5rem;">25</td><td style="padding:0.5rem;"><b>dbconfig</b></td><td style="padding:0.5rem;">DB-backed config storage</td><td style="padding:0.5rem;">\SPP\SPPConfig::get()</td></tr>
        </table>

        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// Load modules in init.php:
\SPP\Module::loadModule('parikshak');
\SPP\Module::loadModule('sppqueue');
if (\SPP\Module::isLoaded('sppai')) { /* use AI */ }
\SPP\Module::setConfig('key', 'value', 'modulename');</pre>

        <!-- ================================================================ -->
        <!-- SECTION 21: CLI Commands -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">21. CLI Commands</h2>
        <p style="color:#475569; line-height:1.8;">All commands are run with <code>php spp.php &lt;command&gt;</code> from the project root.</p>

        <table style="width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem;">
            <tr style="background:#6366f1; color:#fff;"><th style="text-align:left; padding:0.6rem;">Category</th><th style="text-align:left; padding:0.6rem;">Command</th><th style="text-align:left; padding:0.6rem;">What It Does</th></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><b>Generators</b></td><td style="padding:0.6rem;"><code>make:app</code></td><td style="padding:0.6rem;">Create new application</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>make:entity</code></td><td style="padding:0.6rem;">Generate entity class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>make:controller</code></td><td style="padding:0.6rem;">Generate controller class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>make:middleware</code></td><td style="padding:0.6rem;">Generate middleware class</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><b>Database</b></td><td style="padding:0.6rem;"><code>migrate</code></td><td style="padding:0.6rem;">Run migrations</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>migrate:rollback</code></td><td style="padding:0.6rem;">Rollback last migration</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>db:seed</code></td><td style="padding:0.6rem;">Seed database</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><b>Testing</b></td><td style="padding:0.6rem;"><code>test:run</code></td><td style="padding:0.6rem;">Run test suite</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>test:blueprint</code></td><td style="padding:0.6rem;">Auto-generate test stubs</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>test:monkey</code></td><td style="padding:0.6rem;">Fuzz testing</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"><b>Cache</b></td><td style="padding:0.6rem;"><code>cache:clear</code></td><td style="padding:0.6rem;">Clear all caches</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>cache:warmup</code></td><td style="padding:0.6rem;">Pre-build caches</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:0.6rem;"></td><td style="padding:0.6rem;"><code>view:clear</code></td><td style="padding:0.6rem;">Clear compiled Blade views</td></tr>
            <tr style="border-bottom:1px solid #e2e8f0; background:#fafbfc;"><td style="padding:0.6rem;"><b>Deploy</b></td><td style="padding:0.6rem;"><code>deploy</code></td><td style="padding:0.6rem;">Deploy application</td></tr>
        </table>

        <!-- ================================================================ -->
        <!-- SECTION 22: Configuration -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">22. Configuration</h2>
        <p style="color:#475569; line-height:1.8;">SPP uses YAML files for configuration. Key files:</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
# ── spp/etc/global-settings.yml ── (framework-wide)
apps:
  Samvaad:
    base_url: /Samvaad
    table_prefix: Samvaad_
    type: mixed
    shared_group: core
    etc_path: etc/apps/Samvaad
    src_path: src/Samvaad

# ── etc/apps/Samvaad/config.yml ── (app-specific)
app:
  name: "Samvaad"
  debug: true                    # SPP_DEBUG mode
  timezone: "UTC"

# ── Reading config in PHP ──
$value = \SPP\SPPConfig::get('app.name');       // "Samvaad"
$debug = \SPP\SPPConfig::get('app.debug');       // true
\SPP\SPPConfig::set('app.timezone', 'Asia/Tokyo');</pre>

        <!-- ================================================================ -->
        <!-- SECTION 23: Caching -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">23. Caching</h2>
        <p style="color:#475569; line-height:1.8;">SPP caches routes, views, and config for performance. Use CLI to manage.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
// ── Using the cache API ──
\SPP\Cache::put('key', $data, 3600);      // Cache for 1 hour
$data = \SPP\Cache::get('key');            // Retrieve
\SPP\Cache::forget('key');                 // Delete
\SPP\Cache::flush();                       // Clear all

// ── Cache locations ──
// var/cache/system/config.php    — Compiled config
// var/cache/routes_*.php         — Compiled routes
// var/cache/views/               — Compiled Blade templates

// ── CLI commands ──
// php spp.php cache:clear                 — Clear everything
// php spp.php cache:warmup                — Pre-compile caches
// php spp.php view:clear                  — Clear Blade cache only</pre>

        <!-- ================================================================ -->
        <!-- SECTION 24: Logging -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">24. Logging</h2>
        <p style="color:#475569; line-height:1.8;">The <code>spplogger</code> module provides structured logging with automatic log rotation.</p>
        <pre style="background:#0f172a; color:#e2e8f0; padding:1.2rem; border-radius:10px; overflow-x:auto; font-size:0.82rem; line-height:1.7;">
use SPP\Log;

Log::debug('Debug information', ['context' =&gt; $data]);
Log::info('User logged in', ['user_id' =&gt; 42]);
Log::warning('Slow query detected', ['time' =&gt; '2.5s']);
Log::error('Payment failed', ['order_id' =&gt; 123, 'reason' =&gt; $msg]);
Log::critical('Database connection lost');

// Logs are stored in: var/log/
// Format: [2024-01-15 10:30:00] INFO: User logged in {"user_id":42}
// Log rotation is automatic (configurable max file size)</pre>

        <!-- ================================================================ -->
        <!-- SECTION 25: Getting Started Checklist -->
        <!-- ================================================================ -->
        <h2 style="color:#6366f1; margin-top:2.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">25. Getting Started Checklist</h2>
        <p style="color:#475569; line-height:1.8;">Follow these steps to build your first feature in <code>Samvaad</code>:</p>

        <div style="background:#f0fdf4; border-left:4px solid #22c55e; padding:1.2rem 1.5rem; border-radius:0 10px 10px 0; margin:1rem 0;">
            <ol style="color:#475569; line-height:2.2; margin:0; padding-left:1.2rem;">
                <li><b>Explore your app</b> &mdash; Visit <code>/Samvaad/home</code> in your browser</li>
                <li><b>Edit a controller</b> &mdash; Modify <code>src/Samvaad/serv/HomeController.php</code></li>
                <li><b>Edit a Blade view</b> &mdash; Modify <code>resources/Samvaad/views/home.blade.php</code></li>
                <li><b>Create an entity</b> &mdash; Run <code>php spp.php make:entity --app=Samvaad Task</code></li>
                <li><b>Run migrations</b> &mdash; Run <code>php spp.php migrate --app=Samvaad</code></li>
                <li><b>Add a new route</b> &mdash; Edit <code>etc/apps/Samvaad/pages.yml</code></li>
                <li><b>Create a YAML form</b> &mdash; Add a new file in <code>etc/apps/Samvaad/forms/</code></li>
                <li><b>Build a component</b> &mdash; Create a JS file in <code>src/Samvaad/comp/</code></li>
                <li><b>Write tests</b> &mdash; Add tests in <code>src/Samvaad/tests/</code></li>
                <li><b>Run tests</b> &mdash; <code>php spp.php test:run --app=Samvaad</code></li>
                <li><b>Clear cache</b> &mdash; <code>php spp.php cache:clear</code> after route/config changes</li>
                <li><b>Deploy</b> &mdash; <code>php spp.php deploy</code></li>
            </ol>
        </div>

        <div style="margin-top:2rem; padding:1.5rem; background:#eff6ff; border-radius:10px; text-align:center;">
            <p style="color:#3b82f6; font-weight:600; margin:0;">Tip: Delete this guide page once you are comfortable with the framework!</p>
            <p style="color:#64748b; font-size:0.85rem; margin:0.5rem 0 0;">Remove the <code>'guide'</code> route from <code>etc/apps/Samvaad/pages.yml</code> and delete this file.</p>
        </div>

    </div>
</div>