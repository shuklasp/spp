<?php
namespace Lekhak\Modules\LekhakAbTesting;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_ab_testing', '\Lekhak\Modules\LekhakAbTesting\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_ab_testing_config (key TEXT, value TEXT)');
    }
}
