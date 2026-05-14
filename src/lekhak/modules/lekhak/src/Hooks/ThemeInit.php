<?php

namespace SPPMod\Lekhak\Hooks;

use SPPMod\Drishyam\Theme;
use SPPMod\Drishyam\Drishyam;

/**
 * Class ThemeInit
 * Handles Lekhak-specific theme initialization.
 */
class ThemeInit
{
    public static function handle(Theme $theme, Drishyam $drishyam): void
    {
        // Add Lekhak-specific variables to the theme configuration
        $theme->set('lekhak_version', '2.0-Alpha');
        
        // We can also register global Blade variables here if needed
        if (class_exists('\SPPMod\SPPBlade\SPPBlade')) {
            $blade = \SPPMod\SPPBlade\SPPBlade::getInstance()->getEngine();
            $blade->setShare('cms_brand', 'Lekhak CMS');
        }
    }
}
