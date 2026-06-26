<?php

namespace Lekhak\Modules\LekhakModuleVarnish;

/**
 * Integrates with Varnish Cache to purge pages and manage reverse-proxy caching.
 * @configure admin/config/varnish
 */

class LekhakModuleVarnish
{

    private $name = 'varnish';
    private $title = 'Varnish purger';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_varnish_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_varnish_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
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
            $build['#suffix'] .= '<!-- Processed by varnish -->';
        } else {
            $build['#suffix'] = '<!-- Processed by varnish -->';
        }
    }

    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
            'control_terminal' =>
                [
                    'type' => 'text',
                    'title' => 'Varnish Control Terminal',
                    'default' => '127.0.0.1:6082',
                ],
            'secret' =>
                [
                    'type' => 'text',
                    'title' => 'Varnish Secret Key',
                    'default' => '',
                ],
        ];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'varnish',
    'title' => 'Varnish purger',
    'instance' => new LekhakModuleVarnish()
];
