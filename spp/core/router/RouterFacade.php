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
     * Dispatch an internal request or HTTP route.
     */
    public static function dispatch(string $uri, array $params = [])
    {
        // Set legacy context
        \SPP\Scheduler::setContext($uri);

        // Try fluent router first for HTTP requests
        if (isset($_SERVER['REQUEST_METHOD'])) {
            try {
                return \SPP\Core\Router\Router::dispatch($_SERVER['REQUEST_METHOD'], $uri);
            } catch (\SPP\Core\SPPException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
                // Fallthrough to legacy module routing if not found in fluent router
            }
        }
        
        return true;
    }
}
