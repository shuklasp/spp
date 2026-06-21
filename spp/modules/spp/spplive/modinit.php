<?php

namespace SPPMod\SPPLive;

/**
 * SPPLive Module Initialization
 * Prevents SPP Core from throwing ErrorExceptions during module load.
 */

// Initialize SPPLive Orchestrator if necessary
if (class_exists('\\SPPMod\\SPPLive\\SPPLive')) {
    \SPPMod\SPPLive\SPPLive::bootLive();
}
