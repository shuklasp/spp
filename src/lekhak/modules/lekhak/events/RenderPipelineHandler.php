<?php
namespace SPPMod\Lekhak\Events;

use SPP\EventHandler;
use SPPMod\Lekhak\Drivers\BladeDriver;
use SPPMod\Lekhak\Drivers\TwigShimDriver;
use SPPMod\Lekhak\Drivers\WPSimDriver;

class RenderPipelineHandler extends EventHandler
{
    public static function getSubscribedEvents(): array
    {
        return [
            'lekhak_render_pipeline' => 'onRenderPipeline'
        ];
    }

    public function onRenderPipeline(&$params)
    {
        $renderer = $params['renderer'];
        
        // Register Drivers
        if (class_exists(BladeDriver::class)) {
            BladeDriver::register($renderer);
        }
        
        if (class_exists(TwigShimDriver::class)) {
            TwigShimDriver::register($renderer);
        }
        
        if (class_exists(WPSimDriver::class)) {
            WPSimDriver::register($renderer);
        }
    }
}
