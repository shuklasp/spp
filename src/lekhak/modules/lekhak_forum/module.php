<?php
namespace Lekhak\Modules\LekhakForum;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_forum', '\Lekhak\Modules\LekhakForum\Module');
    }
    public static function hook_menu() {
        return ['/community' => ['title' => 'Community', 'callback' => 'render_community']];
    }
}
