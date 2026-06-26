<?php
namespace Lekhak\Modules\LekhakReviews;

class Module
{
    public static function init()
    {
        \Lekhak\ModuleRegistry::register('lekhak_reviews', '\Lekhak\Modules\LekhakReviews\Module');
    }
    public static function hook_install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->execute_query('CREATE TABLE IF NOT EXISTS lekhak_reviews_config (key TEXT, value TEXT)');
    }
}
