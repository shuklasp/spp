<?php

namespace SPP\Core\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Cache;

class RateLimiterMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $decaySeconds;

    public function __construct(int $maxRequests = 100, int $decaySeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->decaySeconds = $decaySeconds;
    }

    public function handle($request, \Closure $next)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = "rate_limit:" . md5($ip);

        // Fetch current hits
        $hits = (int) Cache::get($key);

        if ($hits >= $this->maxRequests) {
            http_response_code(429);
            header('Retry-After: ' . $this->decaySeconds);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.'
            ]);
            exit;
        }

        // Increment hits
        Cache::set($key, $hits + 1, $this->decaySeconds);

        // Add headers to response
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . ($this->maxRequests - $hits - 1));

        return $next($request);
    }
}
