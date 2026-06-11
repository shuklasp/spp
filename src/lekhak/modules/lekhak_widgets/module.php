<?php
namespace Lekhak\Modules\LekhakWidgets;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_widgets', '\Lekhak\Modules\LekhakWidgets\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_widgets_config (key TEXT, value TEXT)');
    }
}
