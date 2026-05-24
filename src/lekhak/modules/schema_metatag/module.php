<?php

namespace Lekhak\Modules\LekhakModuleSchemaMetatag;

/**
 * Adds structured data (JSON-LD) to pages to generate rich snippets in search engines.
 * @configure admin/config/schema_metatag
 */

class LekhakModuleSchemaMetatag {

    private $name = 'schema_metatag';
    private $title = 'Schema.org Metatag';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_schema_metatag_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_schema_metatag_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native routing and page rendering headers.
     */
    public function hook_page_meta_alter(&$meta) {
        // Injects specific metatag/schema headers based on entity context.
        $meta['tags'][] = '<meta name="robots" content="index, follow">';
        $meta['tags'][] = '<link rel="canonical" href="'.(isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].'">';
    }


    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by schema_metatag -->';
        } else {
            $build['#suffix'] = '<!-- Processed by schema_metatag -->';
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
    'machine_name' => 'schema_metatag',
    'title' => 'Schema.org Metatag',
    'instance' => new LekhakModuleSchemaMetatag()
];
