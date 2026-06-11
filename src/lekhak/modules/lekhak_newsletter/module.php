<?php
namespace Lekhak\Modules\LekhakNewsletter;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_newsletter', '\Lekhak\Modules\LekhakNewsletter\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_newsletter_config (key TEXT, value TEXT)');
    }
}
