<?php
namespace Lekhak\Modules\LekhakPwa;

/**
 * Turns the site into a Progressive Web App (PWA) with offline support and installability.
 * @configure admin/config/lekhak_pwa
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_pwa', '\Lekhak\Modules\LekhakPwa\Module');
    }
    public static function hook_page_bottom() {
        return '<!-- LekhakPwa integration active -->';
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
