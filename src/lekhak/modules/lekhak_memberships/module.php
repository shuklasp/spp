<?php
namespace Lekhak\Modules\LekhakMemberships;

/**
 * Manages subscription tiers, role provisioning, and premium content access.
 * @configure admin/config/lekhak_memberships
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_memberships', '\Lekhak\Modules\LekhakMemberships\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_memberships_config (key TEXT, value TEXT)');
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
