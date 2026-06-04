<?php

namespace SPP\Core;

if (!interface_exists(__NAMESPACE__ . '\MiddlewareInterface', false)) {
    /**
     * Interface MiddlewareInterface
     * Defines the contract for SPP Middleware components.
     */
    interface MiddlewareInterface
    {
        /**
         * Handle an incoming request.
         *
         * @param mixed $request The request context
         * @param \Closure $next The next middleware in the pipeline
         * @return mixed The response
         */
        public function handle($request, \Closure $next);
    }
}
