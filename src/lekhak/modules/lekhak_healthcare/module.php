<?php
namespace Lekhak\Modules\LekhakHealthcare;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_healthcare', '\Lekhak\Modules\LekhakHealthcare\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_healthcare_config (key TEXT, value TEXT)');
    }
}
