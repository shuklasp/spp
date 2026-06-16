<?php

namespace SPP\Core\Middleware;

/**
 * ApiAuthMiddleware
 * Intercepts /api/v1/* requests and enforces authentication via JWT or API Keys.
 */
class ApiAuthMiddleware implements \SPP\Core\MiddlewareInterface
{
    public function handle($request, \Closure $next)
    {
        $q = $_GET['q'] ?? '';
        $qPath = trim($q, '/');

        // Only intercept AutoApiRouter routes
        if (!str_starts_with($qPath, 'api/v1/')) {
            return $next($request);
        }

        $parts = explode('/', $qPath);

        // Allow token generation endpoint
        if (count($parts) >= 3 && $parts[1] === 'v1' && $parts[2] === 'auth' && ($parts[3] ?? '') === 'token') {
            return $next($request);
        }

        // Require API Key by default, can be toggled via config
        $requireAuth = \SPP\App::getGlobalSettings('api.require_key') ?? true;

        if ($requireAuth) {
            $authHeader = '';
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            } else {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            }
            
            $token = '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            } elseif (isset($_GET['api_key'])) {
                $token = $_GET['api_key'];
            }

            if (empty($token)) {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Unauthorized. Token required."]);
                exit;
            }

            $isValid = false;

            if (class_exists('\\SPP\\SPPEvent')) {
                $params = new \SPP\EventParams([
                    'token' => $token,
                    'is_valid' => false
                ]);
                \SPP\SPPEvent::fireEvent('api.auth.verify_token', $params);
                $isValid = $params->get('is_valid');
            }

            if (!$isValid) {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Unauthorized. Invalid or expired token."]);
                exit;
            }
        }

        return $next($request);
    }
}
