<?php
namespace Lekhak\Modules\LekhakMemberships;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_memberships', '\Lekhak\Modules\LekhakMemberships\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_memberships_config (key TEXT, value TEXT)');
    }
}
