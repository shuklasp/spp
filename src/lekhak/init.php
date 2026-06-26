<?php
/**
 * Lekhak Application Initialization
 * 
 * This file is loaded by the SPP framework during the bootstrap phase 
 * for the 'lekhak' application context.
 */

// Register any app-specific hooks or event listeners here.
// Lekhak is now a fully decoupled SPP application.

// ── Legacy SPPMod Autoloader ─────────────────────────────────────────────
// The Core framework no longer hardcodes Lekhak directory fallbacks.
// This local autoloader intercepts legacy SPPMod namespaces used by Lekhak 
// modules and routes them to the correct local directory.
spl_autoload_register(function ($className) {
    if (strpos($className, 'SPPMod\\') === 0) {
        $parts = explode('\\', $className);
        array_shift($parts); // Remove SPPMod
        if (empty($parts))
            return;

        $modCamel = array_shift($parts);
        $modDirNameSnake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modCamel));
        $modDirNameLower = strtolower($modCamel);

        $candidates = [
            __DIR__ . '/modules/' . $modDirNameSnake . '/src/' . implode('/', $parts) . '.php',
            __DIR__ . '/modules/' . $modDirNameSnake . '/' . implode('/', $parts) . '.php',
            __DIR__ . '/modules/' . $modDirNameLower . '/src/' . implode('/', $parts) . '.php',
            __DIR__ . '/modules/' . $modDirNameLower . '/' . implode('/', $parts) . '.php',
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }
});

require_once __DIR__ . '/modules/spptheme/events/ThemeEventHandler.php';
\SPP\SPPEvent::listen('event_spp_view_render_theme', [new \SPPMod\SppTheme\Events\ThemeEventHandler('event_spp_view_render_theme'), 'onRenderTheme']);

// ── Module Registry Initialization ──────────────────────────────────────
require_once __DIR__ . '/ModuleRegistry.php';
if (php_sapi_name() !== 'cli') {
    \Lekhak\ModuleRegistry::invokeAll('request_init');
}

// Register PageRenderHookEventHandler
require_once __DIR__ . '/events/PageRenderHookEventHandler.php';
\SPP\SPPEvent::listen('event_spp_view_render_theme', [new \EventHandlers\PageRenderHookEventHandler('event_spp_view_render_theme'), 'onPostTheme'], 100);

// ── Content Workflow Registration ──────────────────────────────────────
// Register editorial workflow states and transitions with the core engine.
if (class_exists('\\SPP\\Core\\WorkflowManager')) {
    require_once __DIR__ . '/workflow/ContentWorkflow.php';
    \App\Lekhak\Workflow\ContentWorkflow::register();
}

// ── Content Permission Provider Registration ───────────────────────────
// Supply Lekhak's content-domain permissions to the core PermissionService.
if (class_exists('\\SPP\\Auth\\PermissionService')) {
    require_once __DIR__ . '/permissions/ContentPermissionProvider.php';
    \SPP\Auth\PermissionService::registerProvider(
        'content',
        new \App\Lekhak\Permissions\ContentPermissionProvider()
    );
}

// ── Multilingual Language Handling ──────────────────────────────────────
// Process ?lang= parameter early in the request if the LanguageManager is available.
if (php_sapi_name() !== 'cli' && class_exists('\\SPP\\I18n\\LanguageManager')) {
    try {
        $db = new \SPPMod\SPPDB\SPPDB();
        $langManager = new \SPP\I18n\LanguageManager($db->getPDO());
        require_once __DIR__ . '/ui/LanguageSwitcher.php';
        \App\Lekhak\UI\LanguageSwitcher::handleRequest($langManager);
    } catch (\Throwable $e) {
        // Language support not available — continue without it
    }
}

if (php_sapi_name() === 'cli') {
    // CLI specific initialization for Lekhak can go here.
}
