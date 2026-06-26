<?php
namespace SPPMod\SPPTheme\Events;

use SPP\EventHandler;
require_once dirname(__DIR__) . '/api/class.thememanager.php';
use SPPMod\SPPTheme\Api\ThemeManager;

/**
 * ThemeEventHandler
 * 
 * Hooks into the ViewPage rendering event to apply themes.
 */
class ThemeEventHandler extends EventHandler
{

    public static function getSubscribedEvents(): array
    {
        return [
            'event_spp_view_render_theme' => 'onRenderTheme'
        ];
    }

    public function onRenderTheme(&$params, $occurence = null)
    {
        // Guarantee idempotent evaluation strictly during the opening event phase
        if ($occurence !== null && $occurence !== 'before')
            return;

        $theme = $params['theme'] ?? null;

        // Dynamically override assigned theme engine directly via browser synchronized site cookies
        if (!empty($_COOKIE['lekhak_site_theme_engine'])) {
            $theme = $_COOKIE['lekhak_site_theme_engine'];
        }

        // Apply fallback premium drop-in theme if unset to guarantee persistent visual presentation
        $theme = $theme ?: 'eduxpro';

        if (!$theme)
            return;

        // Initialize Theme
        if (ThemeManager::setTheme($theme)) {
            // Re-capture output through theme
            ob_start();
            ThemeManager::renderWithTheme($params['html'], $params['pageData']);
            $params['html'] = ob_get_clean();
        }
    }
}
