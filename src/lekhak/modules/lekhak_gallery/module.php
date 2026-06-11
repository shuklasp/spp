<?php
namespace Lekhak\Modules\LekhakGallery;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_gallery', '\Lekhak\Modules\LekhakGallery\Module');
    }
    public static function hook_install() {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_gallery_config (key TEXT, value TEXT)');
    }
}
