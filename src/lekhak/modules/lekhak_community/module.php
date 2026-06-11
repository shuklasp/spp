<?php
namespace Lekhak\Modules\LekhakCommunity;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_community', '\Lekhak\Modules\LekhakCommunity\Module');
    }
    public static function hook_menu() {
        return ['/community' => ['title' => 'Community', 'callback' => 'render_community']];
    }
}
