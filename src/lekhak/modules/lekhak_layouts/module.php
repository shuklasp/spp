<?php

namespace Lekhak\Modules\LekhakModuleDisplaySuite;

/**
 * Provides a drag-and-drop UI to build complex page layouts and place blocks.
 * @configure admin/config/lekhak_layouts
 */

class LekhakModuleDisplaySuite {

    private $name = 'lekhak_layouts';
    private $title = 'Display Suite';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_display_suite_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_display_suite_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
        } catch (\Exception $e) {}
        // Core module initialization logic.
        return true;
    }

    /**
     * Extends native Lekhak Block and View capabilities.
     */
    public function hook_block_alter(&$blocks) {
        // Extends site building capabilities via the block API.
    }


    public function hook_entity_view_alter(&$build, $context = []) {
        // Generic entity display modifier
        if (isset($build['#suffix'])) {
            $build['#suffix'] .= '<!-- Processed by display_suite -->';
        } else {
            $build['#suffix'] = '<!-- Processed by display_suite -->';
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
    'machine_name' => 'lekhak_layouts',
    'title' => 'Display Suite',
    'instance' => new LekhakModuleDisplaySuite()
];
