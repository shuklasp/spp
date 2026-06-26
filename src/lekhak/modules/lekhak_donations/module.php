<?php
namespace Lekhak\Modules\LekhakDonations;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_donations', '\Lekhak\Modules\LekhakDonations\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_donations_config (key TEXT, value TEXT)');
    }
}
