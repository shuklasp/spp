<?php
namespace Lekhak\Modules\LekhakGdpr;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_gdpr', '\Lekhak\Modules\LekhakGdpr\Module');
    }
    public static function hook_page_bottom() {
        return '<!-- LekhakGdpr integration active -->';
    }
}
