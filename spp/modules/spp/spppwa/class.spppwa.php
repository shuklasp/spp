<?php
namespace SPPMod\SPPPWA;

/**
 * SPPPWA
 * 
 * Auto-generates native manifests forcing Android and iOS devices to recognize the entire backend application.
 */
class SPPPWA extends \SPP\SPPObject
{
    /**
     * Outputs a standardized manifest.json specifically configured for PWA functionality.
     */
    public static function serveManifest()
    {
        $appname = \SPP\Module::getConfig('app_name', 'spp') ?: 'SPP Framework App';
        $shortname = \SPP\Module::getConfig('app_short_name', 'spp') ?: 'SPP App';
        $themeColor = \SPP\Module::getConfig('theme_color', 'spp') ?: '#ffffff';

        $manifest = [
            'name' => $appname,
            'short_name' => $shortname,
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => $themeColor,
            'icons' => [
                [
                    'src' => '/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png'
                ],
                [
                    'src' => '/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png'
                ]
            ]
        ];

        header('Content-Type: application/json');
        echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
