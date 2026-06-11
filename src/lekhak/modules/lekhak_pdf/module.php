<?php
namespace Lekhak\Modules\LekhakPdf;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_pdf', '\Lekhak\Modules\LekhakPdf\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_pdf_config (key TEXT, value TEXT)');
    }
}
