<?php
/**
 * Drishyam Theming Engine Module Initialization
 * 
 * Automatically boots the Drishyam engine to ensure runtime route/asset mapping 
 * and theme registration are active framework-wide.
 */
\SPP\SPPEvent::registerEventHandler('event_spp_kernel_boot', function() {
    \SPPMod\Drishyam\Drishyam::getInstance()->boot();
});
