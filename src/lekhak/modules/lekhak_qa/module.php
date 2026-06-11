<?php
namespace Lekhak\Modules\LekhakQa;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_qa', '\Lekhak\Modules\LekhakQa\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_qa_config (key TEXT, value TEXT)');
    }
}
