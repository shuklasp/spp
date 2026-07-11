<?php
namespace App\\testapp\\Middleware;

/**
 * ============================================================================
 * Auth Guard Middleware — testapp
 * ============================================================================
 *
 * HOW MIDDLEWARE WORKS:
 * Middleware runs before the controller/page is executed.
 * If the middleware returns false or redirects, the request is halted.
 *
 * HOW TO USE:
 * Add middleware to a route in pages.yml (if supported) or call
 * in the controller's constructor:
 *   if (!AuthGuard::check()) { redirect to login; }
 * ============================================================================
 */
class AuthGuard
{
    public static function check(): bool
    {
        if (class_exists('\\SPPMod\\SPPAuth\\SPPAuth')) {
            return \\SPPMod\\SPPAuth\\SPPAuth::authSessionExists();
        }
        return false;
    }

    public static function requireAuth(string \$appName = ''): void
    {
        if (!self::check()) {
            \$baseUrl = \\SPP\\App::getBaseUrl(\$appName ?: 'testapp');
            header('Location: ' . \$baseUrl . '/login');
            exit;
        }
    }
}