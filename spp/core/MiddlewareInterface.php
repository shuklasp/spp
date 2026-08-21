<?php
namespace SPP\Core;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param  mixed  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, \Closure $next);
}
