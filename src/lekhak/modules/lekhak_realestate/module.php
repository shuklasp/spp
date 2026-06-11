<?php
namespace Lekhak\Modules\LekhakRealestate;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_realestate', '\Lekhak\Modules\LekhakRealestate\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_realestate_config (key TEXT, value TEXT)');
    }
}
