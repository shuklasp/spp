<?php
namespace Lekhak\Modules\LekhakLightbox;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_lightbox', '\Lekhak\Modules\LekhakLightbox\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_lightbox_config (key TEXT, value TEXT)');
    }
}
