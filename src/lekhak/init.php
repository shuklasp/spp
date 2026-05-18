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

if (php_sapi_name() === 'cli') {
    // CLI specific initialization for Lekhak can go here.
}
