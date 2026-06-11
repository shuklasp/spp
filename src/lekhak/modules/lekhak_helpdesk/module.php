<?php
namespace Lekhak\Modules\LekhakHelpdesk;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_helpdesk', '\Lekhak\Modules\LekhakHelpdesk\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_helpdesk_config (key TEXT, value TEXT)');
    }
}
