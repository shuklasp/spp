<?php
namespace SPPMod\SPPAPI\Middleware;

use SPP\Core\MiddlewareInterface;
use SPP\Core\Request;
use SPP\Core\SPPException;

class ApiAuthMiddleware implements MiddlewareInterface
{

    public function handle($request, $next)
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new SPPException("Unauthorized. Bearer token missing.", 401);
        }

        $token = $matches[1];

        if (!$this->validateToken($token)) {
            throw new SPPException("Unauthorized. Invalid token.", 401);
        }

        return $next($request);
    }

    private function validateToken(string $token): bool
    {
        if (!class_exists('\SPPMod\SPPAPI\JWTAuth')) {
            require_once SPP_MODULES_DIR . '/spp/sppapi/src/JWTAuth.php';
        }
        $secret = \SPP\Module::getConfig('jwt_secret', 'sppapi') ?: getenv('SPP_JWT_SECRET');
        if (!$secret || $secret === 'auto-generated-secret-key-change-me' || $secret === 'default-secret') {
            return false;
        }
        $payload = \SPPMod\SPPAPI\JWTAuth::decode($token, $secret);
        return $payload !== false;
    }
}
