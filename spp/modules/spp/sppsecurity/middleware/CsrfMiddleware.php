<?php
namespace SPPMod\Sppsecurity\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Core\Request;
use SPP\Core\Response;
use SPPMod\Sppsecurity\SPPSecurityService;
use SPP\Core\SPPException;

class CsrfMiddleware implements MiddlewareInterface {
    public function handle($request, $next) {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        
        // Only validate state-changing requests
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            $security = new SPPSecurityService();
            if (!$security->validateCsrfToken($token)) {
                throw new SPPException("CSRF token validation failed.", 403);
            }
        }
        
        return $next($request);
    }
}
