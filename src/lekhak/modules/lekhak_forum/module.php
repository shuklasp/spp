<?php
namespace Lekhak\Modules\LekhakForum;

/**
 * Provides threaded discussion boards, topics, and moderation tools.
 * @configure admin/config/lekhak_forum
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_forum', '\Lekhak\Modules\LekhakForum\Module');
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
