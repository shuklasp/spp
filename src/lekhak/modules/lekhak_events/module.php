<?php
namespace Lekhak\Modules\LekhakEvents;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_events', '\Lekhak\Modules\LekhakEvents\Module');
    }
    public static function hook_entity_info_alter(&$info) {
        $info['lekhak_events'] = ['label' => 'LekhakEvents Data', 'table' => 'lekhak_events_data'];
    }
}
