<?php
/**
 * SPP-UX Module Initialization
 * 
 * Automatically boots the SPP-UX runtime and bridge if active.
 */
if (class_exists('\SPPMod\SPPUX\SPPUX')) {
    \SPPMod\SPPUX\SPPUX::boot();
}
