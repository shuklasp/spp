<?php
namespace Lekhak\Modules\LekhakMigrations;

/**
 * Provides an framework to import and migrate data from external sources or legacy systems.
 * @configure admin/config/lekhak_migrations
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_migrations', '\Lekhak\Modules\LekhakMigrations\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_migrations_config (key TEXT, value TEXT)');
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
