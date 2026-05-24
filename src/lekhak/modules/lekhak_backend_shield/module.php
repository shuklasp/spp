<?php
namespace Lekhak\Modules\LekhakBackendShield;

/**
 * Adds additional security layers and access restrictions to the administrative backend.
 * @configure admin/config/lekhak_backend_shield
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_backend_shield', '\Lekhak\Modules\LekhakBackendShield\Module');
    }
    public static function hook_request_init() {
        // AdminExile: Block backend access without secret key
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
