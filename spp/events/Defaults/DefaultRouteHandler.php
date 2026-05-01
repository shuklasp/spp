<?php
namespace EventHandlers\Defaults;

use SPP\EventHandler;

/**
 * DefaultRouteHandler
 * 
 * Implements the core framework routing logic as an overridable event handler.
 */
class DefaultRouteHandler extends EventHandler {
    
    public function overrideHandler(mixed &$params = []) {
        $uri = $params['uri'];
        $apps = $params['apps'];
        $matchedApp = null;
        $maxLen = -1;

        $appBase = defined('APP_BASE_URI') ? rtrim(APP_BASE_URI, '/') : '';
        
        foreach ($apps as $name => $meta) {
            $baseUrl = $meta['base_url'] ?? '';
            $fullPattern = $appBase . '/' . ltrim($baseUrl, '/');
            $fullPattern = rtrim($fullPattern, '/');
            if ($fullPattern === '') $fullPattern = '/';
            
            $uriLower = strtolower($uri);
            $patternLower = strtolower($fullPattern);
            
            $match = false;
            if ($patternLower === '/') {
                $match = true; 
            } elseif (str_starts_with($uriLower, $patternLower)) {
                $nextChar = substr($uriLower, strlen($patternLower), 1);
                if ($nextChar === '' || $nextChar === '/' || $nextChar === '?') {
                    $match = true;
                }
            }

            if ($match) {
                 if (strlen($fullPattern) > $maxLen) {
                     $maxLen = strlen($fullPattern);
                     $matchedApp = $name;
                 }
            }
        }

        if (!$matchedApp) {
            foreach ($apps as $name => $meta) {
                if (!empty($meta['is_base_app'])) {
                    $matchedApp = $name;
                    break;
                }
            }
            if (!$matchedApp) $matchedApp = 'default';
        }

        $params['context'] = $matchedApp;
    }
}
