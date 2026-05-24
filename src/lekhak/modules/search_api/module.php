<?php

namespace Lekhak\Modules\LekhakModuleSearchApi;

/**
 * A flexible framework for integrating with external search engines like Solr or Elasticsearch.
 * @configure admin/config/search_api
 */

class LekhakModuleSearchApi {

    private $name = 'search_api';
    private $title = 'Search API';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_search_api_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_search_api_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native routing and page rendering headers.
     */
    public function hook_page_meta_alter(&$meta) {
        // Enhances SEO meta parameters.
    }


    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by search_api -->';
        } else {
            $build['#suffix'] = '<!-- Processed by search_api -->';
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
    'machine_name' => 'search_api',
    'title' => 'Search API',
    'instance' => new LekhakModuleSearchApi()
];
