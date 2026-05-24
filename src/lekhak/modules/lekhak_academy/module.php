<?php
namespace Lekhak\Modules\LekhakAcademy;

/**
 * Provides course management, lessons, quizzes, and progression tracking for e-learning.
 * @configure admin/config/lekhak_academy
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_academy', '\Lekhak\Modules\LekhakAcademy\Module');
    }
    public static function hook_entity_info_alter(&$info) {
        $info['lekhak_academy'] = ['label' => 'LekhakAcademy Data', 'table' => 'lekhak_academy_data'];
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
