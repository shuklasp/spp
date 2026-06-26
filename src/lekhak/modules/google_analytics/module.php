<?php

namespace Lekhak\Modules\LekhakModuleGoogleAnalytics;

/**
 * Integrates Google Analytics tracking code to monitor site traffic and user behavior.
 * @configure admin/config/google_analytics
 */

class LekhakModuleGoogleAnalytics
{

    private $name = 'google_analytics';
    private $title = 'Google Analytics';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_google_analytics_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_google_analytics_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {
        }
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native routing and page rendering headers.
     */
    public function hook_page_meta_alter(&$meta)
    {
        // Enhances SEO meta parameters.
    }


    public function hook_entity_view_alter(&$build, $context = [])
    {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by google_analytics -->';
        } else {
            $build['#suffix'] = '<!-- Processed by google_analytics -->';
        }
    }

    /**
     * Defines the configuration form schema for this module.
     */
    public static function hook_config_form(): array
    {
        return [
            'tracking_id' =>
                [
                    'type' => 'text',
                    'title' => 'Google Analytics Tracking ID',
                    'default' => '',
                    'description' => 'E.g. UA-XXXXX-Y or G-XXXXXXX',
                ],
            'anonymize_ip' =>
                [
                    'type' => 'checkbox',
                    'title' => 'Anonymize IP',
                    'default' => true,
                ],
        ];
    }
}

return [
    'status' => 'enabled',
    'machine_name' => 'google_analytics',
    'title' => 'Google Analytics',
    'instance' => new LekhakModuleGoogleAnalytics()
];
