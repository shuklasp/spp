<?php
namespace Lekhak\Modules\LekhakEvents;

/**
 * Manages event calendars, dates, locations, and user RSVPs or registrations.
 * @configure admin/config/lekhak_events
 */

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_events', '\Lekhak\Modules\LekhakEvents\Module');
    }
    public static function hook_entity_info_alter(&$info) {
        $info['lekhak_events'] = ['label' => 'LekhakEvents Data', 'table' => 'lekhak_events_data'];
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
