<?php
namespace Lekhak\Modules\LekhakHealthcare;

/**
 * Specialized features for healthcare sites, including doctor directories and HIPAA-compliant forms.
 * @configure admin/config/lekhak_healthcare
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_healthcare', '\Lekhak\Modules\LekhakHealthcare\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_healthcare_config (key TEXT, value TEXT)');
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
