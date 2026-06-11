<?php
namespace Lekhak\Modules\LekhakPwa;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_pwa', '\Lekhak\Modules\LekhakPwa\Module');
    }
    public static function hook_page_bottom() {
        return '<!-- LekhakPwa integration active -->';
    }
}
