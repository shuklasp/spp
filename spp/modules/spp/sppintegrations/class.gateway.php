<?php
namespace SPPMod\SPPIntegrations;

/**
 * Class IntegrationGateway
 * 
 * HTTP Interceptor Middleware for routed apps (Path 1 CDC).
 * Hooks into SPP Router to intercept POST requests meant for guest applications.
 */
class IntegrationGateway
{
    /**
     * Intercept a request before it reaches the guest application.
     * 
     * @param string $path The incoming request path (e.g. /blog/wp-login.php)
     * @param array $postData The $_POST payload
     */
    public static function intercept(string $path, array $postData): void
    {
        $traceInitialized = false;

        // Example logic for WordPress Registration interception
        if (strpos($path, 'wp-login.php') !== false && isset($_GET['action']) && $_GET['action'] === 'register') {
            
            if (class_exists('\SPPMod\SPPReport\W3CTraceContext')) {
                \SPPMod\SPPReport\W3CTraceContext::startSpan('integration.gateway.intercept');
                $traceInitialized = true;
            }

            $userData = [
                'username' => $postData['user_login'] ?? '',
                'email'    => $postData['user_email'] ?? ''
            ];

            // If we have data, synchronously broadcast to all OTHER apps BEFORE WordPress handles it
            if (!empty($userData['username'])) {
                IntegrationFactory::broadcastUserSync($userData, 'wordpress');
            }
        }
        
        // Example logic for phpBB Registration interception
        if (strpos($path, 'ucp.php') !== false && isset($_GET['mode']) && $_GET['mode'] === 'register') {
            
            $userData = [
                'username' => $postData['username'] ?? '',
                'email'    => $postData['email'] ?? '',
                'password' => $postData['new_password'] ?? ''
            ];

            if (!empty($userData['username'])) {
                IntegrationFactory::broadcastUserSync($userData, 'phpbb');
            }
        }
        
        // After interception, the SPP router continues routing the raw HTTP request to the guest app's directory.

        if ($traceInitialized && class_exists('\SPPMod\SPPReport\W3CTraceContext')) {
            \SPPMod\SPPReport\W3CTraceContext::endSpan();
        }
    }
}
