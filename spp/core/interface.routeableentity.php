<?php

namespace SPP;

/**
 * Interface RouteableEntityInterface
 * Establishes a standard core contract allowing decoupled applications and modules
 * to advertise routable database entities directly to the global view/router discovery engine.
 */
interface RouteableEntityInterface
{
    /**
     * Returns custom routing configuration arrays defining alias fields, base URLs,
     * and identity parameters mapping to this database entity.
     *
     * @return array<string, mixed> Map defining the entity's routing metadata
     */
    public static function getRouteSpecifications(): array;
}
