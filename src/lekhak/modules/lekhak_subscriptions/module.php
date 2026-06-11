<?php
namespace Lekhak\Modules\LekhakSubscriptions;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_subscriptions', '\Lekhak\Modules\LekhakSubscriptions\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_subscriptions_config (key TEXT, value TEXT)');
    }
}
