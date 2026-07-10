<?php

namespace SPPMod\SPPAudit\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Scheduler;

/**
 * TraceContextMiddleware
 * Inspects incoming HTTP/HTMX requests for W3C TraceContext headers (`traceparent`, `tracestate`),
 * establishing a distributed tracing span across microservices and persistent queues.
 */
class TraceContextMiddleware implements MiddlewareInterface
{
    private static ?string $traceparent = null;
    private static ?string $tracestate = null;

    /**
     * Handle an incoming request.
     *
     * @param mixed $request The request context
     * @param \Closure $next The next middleware in the pipeline
     * @return mixed The response
     */
    public function handle($request, \Closure $next)
    {
        // Capture W3C TraceContext headers from request or server environment
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        // Match case-insensitively
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'traceparent') {
                self::$traceparent = $value;
            } elseif (strtolower($key) === 'tracestate') {
                self::$tracestate = $value;
            }
        }

        if (!self::$traceparent && isset($_SERVER['HTTP_TRACEPARENT'])) {
            self::$traceparent = $_SERVER['HTTP_TRACEPARENT'];
        }
        if (!self::$tracestate && isset($_SERVER['HTTP_TRACESTATE'])) {
            self::$tracestate = $_SERVER['HTTP_TRACESTATE'];
        }

        // If no traceparent exists, initialize a fresh compliant W3C traceparent ID
        if (!self::$traceparent) {
            $traceId = bin2hex(random_bytes(16));
            $spanId = bin2hex(random_bytes(8));
            self::$traceparent = "00-{$traceId}-{$spanId}-01";
        }

        // Set in headers if not already present
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header("traceparent: " . self::$traceparent);
            if (self::$tracestate) {
                header("tracestate: " . self::$tracestate);
            }
        }

        return $next($request);
    }

    /**
     * Retrieves the current active traceparent ID.
     */
    public static function getTraceparent(): ?string
    {
        return self::$traceparent;
    }

    /**
     * Retrieves the current active tracestate.
     */
    public static function getTracestate(): ?string
    {
        return self::$tracestate;
    }
}
