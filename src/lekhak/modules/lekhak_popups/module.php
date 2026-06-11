<?php
namespace Lekhak\Modules\LekhakPopups;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_popups', '\Lekhak\Modules\LekhakPopups\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_popups_config (key TEXT, value TEXT)');
    }
}
