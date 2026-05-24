<?php
namespace App\Lekhak;

/**
 * LekhakApp - Custom Application Class for the Lekhak CMS.
 * Core functionality moved to SPP core modules.
 */
class LekhakApp extends \SPP\App {
    public function __construct(string $appname = '', bool $handleerror = true, int $init_level = 4)
    {
        parent::__construct($appname, $handleerror, $init_level);
        
        // Ensure isolation from sppadmin
        \SPP\Module::disableModule('sppadmin');

        // Boot Ecosystem Registry
        if (class_exists('\\SPPMod\\Lekhak\\Core\\ModuleRegistry')) {
            \SPPMod\Lekhak\Core\ModuleRegistry::init();
        }
    }
}
