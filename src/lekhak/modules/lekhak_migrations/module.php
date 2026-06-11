<?php
namespace Lekhak\Modules\LekhakMigrations;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_migrations', '\Lekhak\Modules\LekhakMigrations\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_migrations_config (key TEXT, value TEXT)');
    }
}
