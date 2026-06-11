<?php
namespace Lekhak\Modules\LekhakSearchPro;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_search_pro', '\Lekhak\Modules\LekhakSearchPro\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_search_pro_config (key TEXT, value TEXT)');
    }
}
