<?php
namespace SPP\Core;

if (!interface_exists(__NAMESPACE__ . '\MiddlewareInterface', false)) {
    /**
     * Interface MiddlewareInterface
     *
     * Defines the contract for request/response middleware layers.
     */
    interface MiddlewareInterface
    {
        /**
         * Handle the incoming request.
         *
         * @param mixed $request The request data/object
         * @param \Closure $next The next middleware in the stack
         * @return mixed The resulting response
         */
        public function handle($request, \Closure $next);
    }
}
