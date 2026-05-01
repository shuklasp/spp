<?php
namespace EventHandlers\Defaults;

use SPP\EventHandler;

/**
 * DefaultViewRenderHandler
 * 
 * Implements the core framework view rendering logic as an overridable event handler.
 */
class DefaultViewRenderHandler extends EventHandler {
    
    public function overrideHandler(mixed &$params = []) {
        $filename = $params['filename'];
        $pageData = $params['pageData'];
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Handle Blade templates
        if ($ext === 'blade' || str_ends_with($filename, '.blade.php')) {
            if (\SPP\Module::isEnabled('sppblade')) {
                if (class_exists('\SPPMod\SPPBlade\SPPBlade')) {
                    echo \SPPMod\SPPBlade\SPPBlade::render($filename, $pageData);
                    return;
                }
            }
        }

        // Default to standard PHP include
        extract($pageData);
        include($filename);
    }
}
