<?php
namespace SPPMod\SPPAPI\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Core\Request;
use SPP\Core\SPPException;

class ApiThrottleMiddleware implements MiddlewareInterface
{

    public function handle($request, $next)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'api_throttle_' . $ip;

        $cache = \SPP\Module::getModule('sppcache');
        if ($cache && $cache->isActive()) {
            $requests = \SPP\Cache::get($key) ?: 0;
            if ($requests > 100) {
                throw new SPPException("Too many requests. Please try again later.", 429);
            }
            \SPP\Cache::set($key, $requests + 1, 60); // 100 requests per minute
        }

        return $next($request);
    }
}
