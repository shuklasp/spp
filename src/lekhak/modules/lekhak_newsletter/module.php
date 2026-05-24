<?php
namespace Lekhak\Modules\LekhakNewsletter;

/**
 * Manages email subscriber lists and integrates with mass-mailing campaign services.
 * @configure admin/config/lekhak_newsletter
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_newsletter', '\Lekhak\Modules\LekhakNewsletter\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_newsletter_config (key TEXT, value TEXT)');
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
