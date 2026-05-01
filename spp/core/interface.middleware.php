<?php
namespace SPP\Core;

/**
 * Interface MiddlewareInterface
 * 
 * Defines the contract for request/response middleware layers.
 * Follows the PSR-15 inspired 'Onion' pattern.
 */
interface MiddlewareInterface {
    /**
     * Handle the incoming request.
     * 
     * @param mixed    $request The request data/object
     * @param callable $next    The next middleware in the stack
     * @return mixed            The resulting response
     */
    public function handle($request, callable $next);
}
