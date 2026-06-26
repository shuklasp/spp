<?php
namespace SPPMod\SPPSecurity\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Core\Request;
use SPP\Core\Response;
use SPPMod\SPPSecurity\SPPSecurityService;
use SPP\Core\SPPException;

class ThrottleMiddleware implements MiddlewareInterface
{

    private $max;
    private $decay;

    public function __construct(int $max = 60, int $decay = 60)
    {
        $this->max = $max;
        $this->decay = $decay;
    }

    public function handle($request, $next)
    {
        // Use IP address as the default throttle key
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
        $key = 'throttle:' . $ip;

        $security = new SPPSecurityService();
        if (!$security->rateLimit($key, $this->max, $this->decay)) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . $this->decay);
            throw new SPPException("Too Many Requests. Please slow down.", 429);
        }

        return $next($request);
    }
}
