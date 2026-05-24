<?php
namespace Lekhak\Modules\LekhakAuditTrail;

/**
 * Logs all administrative actions and system changes for accountability and security auditing.
 * @configure admin/config/lekhak_audit_trail
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_audit_trail', '\Lekhak\Modules\LekhakAuditTrail\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_audit_trail_config (key TEXT, value TEXT)');
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
