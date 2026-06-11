<?php
namespace Lekhak\Modules\LekhakBackendShield;

class Module {
    public static function init() {
        \Lekhak\ModuleRegistry::register('lekhak_backend_shield', '\Lekhak\Modules\LekhakBackendShield\Module');
    }
    public static function hook_request_init() {
        // AdminExile: Block backend access without secret key
    }
}
