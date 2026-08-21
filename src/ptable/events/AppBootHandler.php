<?php
namespace App\ptable\Events;

/**
 * ============================================================================
 * Event Handler — ptable
 * ============================================================================
 *
 * HOW EVENTS WORK:
 * The SPP event system follows a publish/subscribe pattern.
 *
 * REGISTER (in init.php):
 *   \SPP\SPPEvent::listen('event_name', [new AppBootHandler(), 'handle']);
 *
 * OR REGISTER (in etc/events.yml):
 *   events:
 *     event_name:
 *       - \App\ptable\Events\AppBootHandler
 *
 * FIRE CUSTOM EVENTS:
 *   \SPP\SPPEvent::fireEvent('my.custom.event', new \SPP\EventParams($data));
 *
 * BUILT-IN EVENTS:
 *   PageNotFound              — No route matched
 *   event_spp_view_render_theme — Before theme rendering
 *   event_spp_page_render      — During page render
 *   DefaultNotFound            — Missing pages.yml default
 * ============================================================================
 */
class AppBootHandler extends \SPP\EventHandler
{
    public function afterHandler(&$params = [])
    {
        // This runs when the registered event fires
        if (defined('SPP_DEBUG') && SPP_DEBUG) {
            @file_put_contents(
                SPP_APP_DIR . '/var/logs/ptable_events.log',
                '[' . date('Y-m-d H:i:s') . "] Event handled by AppBootHandler\n",
                FILE_APPEND
            );
        }
    }
}