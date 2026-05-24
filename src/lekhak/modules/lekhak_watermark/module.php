<?php
namespace Lekhak\Modules\LekhakWatermark;

/**
 * Automatically applies visual watermarks to uploaded images to protect copyrights.
 * @configure admin/config/lekhak_watermark
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_watermark', '\Lekhak\Modules\LekhakWatermark\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_watermark_config (key TEXT, value TEXT)');
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
