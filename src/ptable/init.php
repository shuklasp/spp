<?php
/**
 * ============================================================================
 * Application Boot — ptable
 * ============================================================================
 *
 * HOW THIS WORKS:
 * This file is loaded automatically by the SPP framework during bootstrap
 * when the 'ptable' application context is active. It runs ONCE per request.
 *
 * USE THIS FOR:
 *   - Registering autoloaders for app-specific namespaces
 *   - Registering event listeners
 *   - Setting up custom middleware
 *   - Configuring services
 *
 * DO NOT USE FOR:
 *   - Output (echo/print) — this runs before any rendering
 *   - Heavy processing — keep boot fast
 * ============================================================================
 */

// ── App-Specific Autoloader ─────────────────────────────────────────────
// Maps the \App\ptable\ namespace to this app's directory.
// This allows you to use classes like \App\ptable\Serv\HomeController
spl_autoload_register(function ($className) {
    // Only handle our app namespace
    $prefix = 'App\\ptable\\';
    if (strpos($className, $prefix) !== 0) return;

    $relative = substr($className, strlen($prefix));
    $parts = explode('\\', $relative);

    // Map namespace to directory (Serv → serv, Entities → entities, etc.)
    $file = __DIR__ . '/' . strtolower($parts[0]);
    if (count($parts) > 1) {
        $file .= '/' . implode('/', array_slice($parts, 1));
    }
    $file .= '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Event Registration ──────────────────────────────────────────────────
// Register event listeners for framework lifecycle events.
// Uncomment to activate:
//
// \SPP\SPPEvent::listen('PageNotFound', function ($params) {
//     // Custom 404 handling
//     echo "<h1>Page not found in ptable</h1>";
// });
//
// \SPP\SPPEvent::listen('app.boot', function ($params) {
//     // Runs when app context is set
// });

// ── SPP-UX Auto-Boot ────────────────────────────────────────────────────
// Automatically register SPP-UX assets for all pages in this app.
// This means @sppux directives and SPPUX::render() work on any page.
if (php_sapi_name() !== 'cli' && class_exists('\\SPPMod\\Drishyam\\SPPUX')) {
    \SPPMod\Drishyam\SPPUX::boot('ptable');
}

// ── Dynamic Asset Inclusion ───────────────────────────────────
// Automatically injects mapped CSS and JS assets from module.yml
// configurations via the secure AssetRouter alias system.
if (php_sapi_name() !== 'cli') {
    \\SPP\\App::includeAssets();
}