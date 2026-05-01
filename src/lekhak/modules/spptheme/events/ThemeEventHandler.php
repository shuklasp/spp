<?php
namespace SPPMod\SppTheme\Events;

use SPP\EventHandler;
use SPPMod\SppTheme\Api\ThemeManager;

/**
 * ThemeEventHandler
 * 
 * Hooks into the ViewPage rendering event to apply themes.
 */
class ThemeEventHandler extends EventHandler {
    
    public static function getSubscribedEvents() {
        return [
            'event_spp_view_render_theme' => 'onRenderTheme'
        ];
    }

    public function onRenderTheme(&$params) {
        $theme = $params['theme'] ?? null;
        if (!$theme) return;

        // Initialize Theme
        if (ThemeManager::setTheme($theme)) {
            // Re-capture output through theme
            ob_start();
            ThemeManager::renderWithTheme($params['html'], $params['pageData']);
            $params['html'] = ob_get_clean();
        }
    }
}
