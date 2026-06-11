<?php
namespace Lekhak\Modules\LekhakDocuments;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_documents', '\Lekhak\Modules\LekhakDocuments\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_documents_config (key TEXT, value TEXT)');
    }
}
