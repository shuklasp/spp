<?php

/**
 * SPP-UX Module Initialization
 *
 * Automatically boots the SPP-UX runtime and bridge if active.
 */
\SPP\SPPEvent::registerEventHandler('event_spp_kernel_boot', function () {
    \SPPMod\SPPUX\SPPUX::boot();
});
