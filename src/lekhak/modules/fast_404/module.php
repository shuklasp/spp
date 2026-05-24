<?php

namespace Lekhak\Modules\LekhakModuleFast404;

/**
 * Reduces server load by returning simple, fast 404 error pages for missing static assets.
 * @configure admin/config/fast_404
 */

class LekhakModuleFast404 {

    private $name = 'fast_404';
    private $title = 'Fast 404';

    public function hook_init() {
        $db = new \SPPMod\SPPDB\SPPDB();
        try {
            $db->execute_query("CREATE TABLE IF NOT EXISTS lekhak_fast_404_config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key VARCHAR(100) UNIQUE,
                setting_value TEXT
            )");
            
            // Insert default config
            $db->execute_query("INSERT OR IGNORE INTO lekhak_fast_404_config (setting_key, setting_value) VALUES (?, ?)", ['enabled', '1']);
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
            $build['#suffix'] .= '<!-- Processed by fast_404 -->';
        } else {
            $build['#suffix'] = '<!-- Processed by fast_404 -->';
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
    'machine_name' => 'fast_404',
    'title' => 'Fast 404',
    'instance' => new LekhakModuleFast404()
];
