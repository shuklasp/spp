<?php
namespace Lekhak\Modules\LekhakWidgets;

/**
 * A collection of small, reusable UI widgets like weather, clocks, or quick links.
 * @configure admin/config/lekhak_widgets
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_widgets', '\Lekhak\Modules\LekhakWidgets\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_widgets_config (key TEXT, value TEXT)');
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
