<?php
namespace Lekhak\Modules\LekhakAuthors;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_authors', '\Lekhak\Modules\LekhakAuthors\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_authors_config (key TEXT, value TEXT)');
    }
}
