<?php
declare(strict_types=1);

namespace SPPMod\DBConfig;

if (class_exists('\\SPP\\SPPConfig')) {
    // Register DB setting handler
    if (class_exists('\\SPPMod\\DBSettings\\DBSettings')) {
        \SPP\SPPConfig::registerProvider('db', 
            ['\\SPPMod\\DBSettings\\DBSettings', 'get'], 
            function($key, $value, $appname) {
                \SPPMod\DBSettings\DBSettings::set($key, $value, $appname);
            }
        );
    }

    // Register Module setting handler
    \SPP\SPPConfig::registerProvider('mod', 
        function($key, $appname, $fullKey) {
            $parts = explode(':', $key);
            if (count($parts) >= 2) {
                $modname = array_shift($parts);
                $modkey = implode(':', $parts);
                return \SPP\Module::getConfig($modkey, $modname, $appname);
            }
            return null;
        },
        function($key, $value, $appname, $fullKey) {
            $parts = explode(':', $key);
            if (count($parts) >= 2) {
                $modname = array_shift($parts);
                $modkey = implode(':', $parts);
                \SPP\Module::setConfig($modkey, $value, $modname, $appname);
            }
        }
    );
}
