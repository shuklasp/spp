<?php
namespace EventHandlers;

/**
 * Sample localized application Event Handler.
 * Discovered dynamically by SPPEvent engine under /src/eventapp/events directory.
 */
class UserRegisteredHandler extends \SPP\EventHandler {
    public function afterHandler(&$params = []) {
        // Intercept event payload natively inside application boundaries
        if (defined('SPP_DEBUG') && SPP_DEBUG) {
            @file_put_contents(SPP_APP_DIR . '/var/logs/eventapp_events.log', '['.date('Y-m-d H:i:s').'] Intercepted UserRegistered target event flawlessly.\n', FILE_APPEND);
        }
    }
}
