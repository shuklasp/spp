<?php
namespace Lekhak\Modules\LekhakAbTesting;

/**
 * Enables A/B split testing to compare content variations and optimize conversion rates.
 * @configure admin/config/lekhak_ab_testing
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_ab_testing', '\Lekhak\Modules\LekhakAbTesting\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_ab_testing_config (`key` TEXT, `value` TEXT)');
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
