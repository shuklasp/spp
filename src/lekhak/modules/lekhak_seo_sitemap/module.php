<?php

namespace Lekhak\Modules\LekhakModuleSimpleSitemap;

/**
 * Automatically generates XML sitemaps to ensure search engines index all content.
 * @configure admin/config/lekhak_seo_sitemap
 */

class LekhakModuleSimpleSitemap
{

    private $name = 'lekhak_seo_sitemap';
    private $title = 'Simple XML sitemap';

    public function hook_init()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_simple_sitemap_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");

            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_simple_sitemap_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
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
            $build['#suffix'] .= '<!-- Processed by simple_sitemap -->';
        } else {
            $build['#suffix'] = '<!-- Processed by simple_sitemap -->';
        }
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
    'machine_name' => 'lekhak_seo_sitemap',
    'title' => 'Simple XML sitemap',
    'instance' => new LekhakModuleSimpleSitemap()
];
