<?php
namespace SPP\Core\Middleware;

use SPP\SPPSession;
use SPP\Exceptions\SPPException;

/**
 * Class CSRFMiddleware
 * Protects state-changing requests by validating CSRF tokens.
 */
class CSRFMiddleware implements \SPP\Core\MiddlewareInterface
{
    /**
     * Handle the request and validate CSRF token for admin actions.
     */
    public function handle($request, \Closure $next)
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Broaden protection to any api.php endpoint (Admin or App contexts)
        if (str_contains($scriptName, '/api.php') || str_contains($scriptName, '/sppux_api')) {
            $action = $request['action'] ?? '';
            
            // Skip check for login/auth initialization
            if ($action !== 'login' && $action !== 'check_auth') {
                
                // Try to get token from body/query OR standard X-CSRF-TOKEN header
                $submittedToken = $_REQUEST['csrf_token'] ?? '';
                if (!$submittedToken) {
                    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
                }
                
                try {
                    $sessionToken = @SPPSession::getCsrfToken();
                    if (!$submittedToken || $submittedToken !== $sessionToken) {
                        // Log potential security breach in future
                        http_response_code(419);
                        throw new SPPException("CSRF Token validation failed or session expired. Please refresh the page.", 419);
                    }
                } catch (\Exception $e) {
                    if ($action !== 'login') {
                         throw $e;
                    }
                }
            }
        }

        return $next($request);
    }
}
