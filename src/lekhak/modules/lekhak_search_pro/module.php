<?php
namespace Lekhak\Modules\LekhakSearchPro;

/**
 * An advanced search interface with faceted filtering, autocomplete, and robust indexing.
 * @configure admin/config/lekhak_search_pro
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_search_pro', '\Lekhak\Modules\LekhakSearchPro\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_search_pro_config (key TEXT, value TEXT)');
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
