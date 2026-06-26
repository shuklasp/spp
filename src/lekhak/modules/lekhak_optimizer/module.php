<?php

namespace Lekhak\Modules\LekhakModuleAdvagg;

/**
 * Analyzes site performance and implements caching/minification strategies to speed up load times.
 * @configure admin/config/lekhak_optimizer
 */

class LekhakModuleAdvagg
{

    private $name = 'lekhak_optimizer';
    private $title = 'Advanced CSS/JS Aggregation';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_advagg_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_advagg_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
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
        // Implements on-the-fly minification and gzip of assets.
        return 'advagg_cache_handler';
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by advagg -->';
        } else {
            $build['#suffix'] = '<!-- Processed by advagg -->';
        }
    }


    // JCH Optimize Extension
    public static function hook_page_render_alter(&$html)
    {
        // HTML Minification
        $html = preg_replace(["/\s+/", "/\s*([<>])\s*/"], [" ", "$1"], $html);
        // Async/Defer script tag replacement
        $html = str_replace("<script src=", "<script defer src=", $html);
    }


    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
            'enabled' =>
                [
                    'type' => 'checkbox',
                    'title' => 'Enable advanced features',
                    'default' => true,
                ],
            'log_level' =>
                [
                    'type' => 'select',
                    'title' => 'Log Level',
                    'options' =>
                        [
                            'info' => 'Info',
                            'warning' => 'Warning',
                            'error' => 'Error',
                        ],
                    'default' => 'warning',
                ],
        ];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'lekhak_optimizer',
    'title' => 'Advanced CSS/JS Aggregation',
    'instance' => new LekhakModuleAdvagg()
];
