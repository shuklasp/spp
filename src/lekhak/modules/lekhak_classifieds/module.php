<?php
namespace Lekhak\Modules\LekhakClassifieds;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_classifieds', '\Lekhak\Modules\LekhakClassifieds\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_classifieds_config (key TEXT, value TEXT)');
    }
}
