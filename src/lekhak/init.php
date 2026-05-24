<?php
/**
 * Lekhak Application Initialization
 * 
 * This file is loaded by the SPP framework during the bootstrap phase 
 * for the 'lekhak' application context.
 */

// Register any app-specific hooks or event listeners here.
// Lekhak is now a fully decoupled SPP application.
require_once __DIR__ . '/modules/spptheme/events/ThemeEventHandler.php';
\SPP\SPPEvent::registerHandler('event_spp_view_render_theme', '\\SPPMod\\SppTheme\\Events\\ThemeEventHandler', false, 'onRenderTheme');

// ── Module Registry Initialization ──────────────────────────────────────
require_once __DIR__ . '/ModuleRegistry.php';
if (php_sapi_name() !== 'cli') {
    \Lekhak\ModuleRegistry::invokeAll('request_init');
}

// Register PageRenderHookEventHandler
require_once __DIR__ . '/events/PageRenderHookEventHandler.php';
\SPP\SPPEvent::registerHandler('event_spp_view_render_theme', '\\EventHandlers\\PageRenderHookEventHandler', false, 'onPostTheme', 100);

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
