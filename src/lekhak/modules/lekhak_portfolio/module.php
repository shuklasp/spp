<?php
namespace Lekhak\Modules\LekhakPortfolio;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_portfolio', '\Lekhak\Modules\LekhakPortfolio\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_portfolio_config (key TEXT, value TEXT)');
    }
}
