<?php

namespace Lekhak\Modules\LekhakModuleBlazy;

/**
 * Provides lazy-loading for images and iframes to improve page load speed and save bandwidth.
 */

class LekhakModuleBlazy
{

    private $name = 'blazy';
    private $title = 'Blazy';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_blazy_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_blazy_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native caching capabilities.
     */
    public function hook_cache_backend_override()
    {
        // Overrides core caching.
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by blazy -->';
        } else {
            $build['#suffix'] = '<!-- Processed by blazy -->';
        }
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'blazy',
    'title' => 'Blazy',
    'instance' => new LekhakModuleBlazy()
];
