<?php
namespace Lekhak\Modules\LekhakAcademy;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_academy', '\Lekhak\Modules\LekhakAcademy\Module');
    }
    public static function hook_entity_info_alter(&$info) {
        $info['lekhak_academy'] = ['label' => 'LekhakAcademy Data', 'table' => 'lekhak_academy_data'];
    }
}
