<?php
namespace Lekhak\Modules\LekhakCommunity;

/**
 * Provides community features including user profiles, friending, and activity streams.
 * @configure admin/config/lekhak_community
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_community', '\Lekhak\Modules\LekhakCommunity\Module');
    }
    public static function hook_menu() {
        return ['/community' => ['title' => 'Community', 'callback' => 'render_community']];
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
