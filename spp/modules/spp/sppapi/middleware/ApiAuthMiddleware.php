<?php
namespace SPPMod\Sppapi\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Core\Request;
use SPP\Core\SPPException;

class ApiAuthMiddleware implements MiddlewareInterface {
    
    public function handle($request, $next) {
        // Bearer Token Validation
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new SPPException("Unauthorized. Bearer token missing.", 401);
        }

        $token = $matches[1];
        
        // This is a placeholder for token validation (e.g. JWT or DB token check)
        // If we had the sppauth module ready, we would call it here.
        if (!$this->validateToken($token)) {
            throw new SPPException("Unauthorized. Invalid token.", 401);
        }

        return $next($request);
    }
    
    private function validateToken(string $token): bool {
        // Mock validation for now until sppauth is fully unified
        // E.g. Check if the token is valid. Let's assume 'test-token' is valid.
        return $token === 'test-token' || strlen($token) > 20;
    }
}
