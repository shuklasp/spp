<?php

namespace SPP\Core\Router;

/**
 * Class RouterFacade
 *
 * Part of the Phase 2 Decoupling Strategy.
 * This facade serves as the standard entry point for routing logic,
 * allowing the underlying legacy routing mechanisms to be safely refactored
 * without breaking application-level code.
 */
class RouterFacade
{
    /**
     * Get the active route or context.
     */
    public static function getActiveRoute(): string
    {
        return \SPP\Scheduler::getContext();
    }

    /**
     * Dispatch an internal request.
     */
    public static function dispatch(string $uri, array $params = [])
    {
        // Delegates to the legacy internal dispatcher for now
        // Eventually, the full routing engine will be moved into this namespace.
        return \SPP\Scheduler::setContext($uri);
    }
}
