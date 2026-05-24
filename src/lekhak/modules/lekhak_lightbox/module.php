<?php
namespace Lekhak\Modules\LekhakLightbox;

/**
 * Opens images and media in a modal overlay over the current page.
 * @configure admin/config/lekhak_lightbox
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_lightbox', '\Lekhak\Modules\LekhakLightbox\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_lightbox_config (key TEXT, value TEXT)');
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
