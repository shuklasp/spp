<?php

/**
 * SPP-UX Module Initialization
 *
 * Automatically boots the SPP-UX runtime and bridge if active.
 */
\SPP\SPPEvent::listen('event_spp_kernel_boot', function () {
    \SPPMod\Drishyam\SPPUX::boot();
});
