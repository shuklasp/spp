<?php
namespace Lekhak\Modules\LekhakJournal;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_journal', '\Lekhak\Modules\LekhakJournal\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_journal_config (key TEXT, value TEXT)');
    }
}
