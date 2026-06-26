<?php
namespace Lekhak\Modules\LekhakWatermark;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_watermark', '\Lekhak\Modules\LekhakWatermark\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_watermark_config (key TEXT, value TEXT)');
    }
}
