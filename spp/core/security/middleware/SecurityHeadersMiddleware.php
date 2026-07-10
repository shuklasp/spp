<?php
namespace SPP\Core\Security\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Core\Request;
use SPP\Core\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle($request, $next)
    {
        // Send request down the pipeline
        $response = $next($request);

        // We assume response is just returned or output is buffered,
        // but adding headers directly using PHP header() function
        // since SPP might not wrap all headers in the Response object yet.

        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            // header("Content-Security-Policy: default-src 'self'");
        }

        return $response;
    }
}
