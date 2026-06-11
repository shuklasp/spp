<?php
namespace Lekhak\Modules\LekhakReadingTime;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_reading_time', '\Lekhak\Modules\LekhakReadingTime\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_reading_time_config (key TEXT, value TEXT)');
    }
}
