<?php
namespace Lekhak\Modules\LekhakAffiliates;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_affiliates', '\Lekhak\Modules\LekhakAffiliates\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_affiliates_config (key TEXT, value TEXT)');
    }
}
