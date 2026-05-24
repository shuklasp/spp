<?php
namespace Lekhak\Modules\LekhakGdpr;

/**
 * Helps the site comply with GDPR by managing user consent, data exports, and right-to-be-forgotten requests.
 * @configure admin/config/lekhak_gdpr
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_gdpr', '\Lekhak\Modules\LekhakGdpr\Module');
    }
    public static function hook_page_bottom() {
        return '<!-- LekhakGdpr integration active -->';
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
