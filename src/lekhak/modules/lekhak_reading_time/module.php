<?php
namespace Lekhak\Modules\LekhakReadingTime;

/**
 * Calculates and displays the estimated reading time for articles and long-form content.
 * @configure admin/config/lekhak_reading_time
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_reading_time', '\Lekhak\Modules\LekhakReadingTime\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_reading_time_config (key TEXT, value TEXT)');
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
