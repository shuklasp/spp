<?php
namespace Lekhak\Modules\LekhakHelpdesk;

/**
 * A ticketing system to manage user support requests and customer service communication.
 * @configure admin/config/lekhak_helpdesk
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_helpdesk', '\Lekhak\Modules\LekhakHelpdesk\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_helpdesk_config (key TEXT, value TEXT)');
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
