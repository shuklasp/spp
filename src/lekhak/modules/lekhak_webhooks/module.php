<?php
namespace Lekhak\Modules\LekhakWebhooks;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_webhooks', '\Lekhak\Modules\LekhakWebhooks\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_webhooks_config (key TEXT, value TEXT)');
    }
}
