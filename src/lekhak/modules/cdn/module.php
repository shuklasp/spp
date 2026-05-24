<?php

namespace Lekhak\Modules\LekhakModuleCdn;

/**
 * Integrates content delivery networks (CDNs) to serve static assets faster globally.
 */

class LekhakModuleCdn {

    private $name = 'cdn';
    private $title = 'CDN';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_cdn_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_cdn_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native caching capabilities.
     */
    public function hook_cache_backend_override() {
        // Overrides core caching.
    }


    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by cdn -->';
        } else {
            $build['#suffix'] = '<!-- Processed by cdn -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'cdn',
    'title' => 'CDN',
    'instance' => new LekhakModuleCdn()
];
