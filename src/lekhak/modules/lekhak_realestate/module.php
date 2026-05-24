<?php
namespace Lekhak\Modules\LekhakRealestate;

/**
 * Specialized features for property listings, real estate agents, and map integrations.
 * @configure admin/config/lekhak_realestate
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_realestate', '\Lekhak\Modules\LekhakRealestate\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_realestate_config (key TEXT, value TEXT)');
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
