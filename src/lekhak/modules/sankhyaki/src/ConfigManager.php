<?php
namespace Lekhak\Modules\Sankhyaki;

use Symfony\Component\Yaml\Yaml;
use SPP\App;

class ConfigManager {
    
    private static function getConfigPath() {
        return App::getApp()->getAppConfDir() . '/sankhyaki.yml';
    }

    public static function getSettings() {
        $path = self::getConfigPath();
        $defaults = [
            'retention_days' => 30,
            'ip_privacy' => 'plain' // 'plain' or 'hash'
        ];

        if (file_exists($path)) {
            $config = Yaml::parseFile($path);
            if (is_array($config)) {
                return array_merge($defaults, $config);
            }
        }
        return $defaults;
    }

    public static function saveSettings($settings) {
        $path = self::getConfigPath();
        $current = self::getSettings();
        $newSettings = array_merge($current, $settings);
        
        file_put_contents($path, Yaml::dump($newSettings, 4, 2));
        return $newSettings;
    }
}
