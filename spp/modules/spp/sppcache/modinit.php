<?php

namespace SPPMod\SPPCache;

/**
 * SPPCache Module Initialization
 * Registers decoupled event listeners for automatic cache invalidation on entity changes.
 */

if (class_exists('\\SPP\\SPPEvent')) {
    \SPP\SPPEvent::listen('entity:after_save', function($entity) {
        if ($entity instanceof \SPP\EventParams) {
            $entity = $entity->get('entity');
        }
        if (is_object($entity) && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $table = method_exists($entity, 'getTable') ? $entity->getTable() : strtolower((new \ReflectionClass($entity))->getShortName());
            if (method_exists('\\SPPMod\\SPPCache\\SPPCacheManager', 'invalidateByTag')) {
                \SPPMod\SPPCache\SPPCacheManager::invalidateByTag("entity.{$table}");
            }
        }
    });

    \SPP\SPPEvent::listen('entity:deleted', function($entity) {
        if ($entity instanceof \SPP\EventParams) {
            $entity = $entity->get('entity');
        }
        if (is_object($entity) && class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            $table = method_exists($entity, 'getTable') ? $entity->getTable() : strtolower((new \ReflectionClass($entity))->getShortName());
            if (method_exists('\\SPPMod\\SPPCache\\SPPCacheManager', 'invalidateByTag')) {
                \SPPMod\SPPCache\SPPCacheManager::invalidateByTag("entity.{$table}");
            }
        }
    });
}
