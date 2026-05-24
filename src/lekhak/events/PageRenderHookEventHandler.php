<?php
namespace EventHandlers;

use SPP\EventHandler;

class PageRenderHookEventHandler extends EventHandler {
    public static function getSubscribedEvents(): array {
        return [
            'event_spp_view_render_theme' => ['onPostTheme', 100] // Priority 100 to run after ThemeEventHandler (500)
        ];
    }

    public function onPostTheme(&$params, $occurence = null) {
        if ($occurence !== null && $occurence !== 'before') return;
        
        if (isset($params['html']) && class_exists('\\Lekhak\\ModuleRegistry')) {
            \Lekhak\ModuleRegistry::invokeAlter('page_render', $params['html']);
        }
    }
}
