<?php
namespace Lekhak\Modules\LekhakGlossary;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_glossary', '\Lekhak\Modules\LekhakGlossary\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_glossary_config (key TEXT, value TEXT)');
    }
}
