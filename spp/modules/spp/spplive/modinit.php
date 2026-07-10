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

if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::listen('entity:after_save', function($entity) {
        if (class_exists('\\SPPMod\\SPPLive\\SPPLive')) {
            \SPPMod\SPPLive\SPPLive::broadcastEntityEvent($entity, 'updated');
        }
    });

    \SPP\SPPEvent::listen('entity:deleted', function($entity) {
        if (class_exists('\\SPPMod\\SPPLive\\SPPLive')) {
            \SPPMod\SPPLive\SPPLive::broadcastEntityEvent($entity, 'deleted');
        }
    });
}
