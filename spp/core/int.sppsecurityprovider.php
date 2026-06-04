<?php
namespace SPP\Core;

/**
 * Interface SPPSecurityProvider
 * 
 * Core contract for security operations. Implementation is provided
 * by the modular `sppsecurity` module.
 */
interface SPPSecurityProvider {
    /**
     * Generate a cryptographically secure CSRF token.
     *
     * @return string
     */
    public function generateCsrfToken(): string;

    /**
     * Validate a given CSRF token against the current session.
     *
     * @param string $token
     * @return bool
     */
    public function validateCsrfToken(string $token): bool;

    /**
     * Sanitize input against XSS attacks based on the context.
     *
     * @param string $input
     * @param string $context 'html', 'attribute', 'javascript', 'url'
     * @return string
     */
    public function sanitize(string $input, string $context = 'html'): string;

    /**
     * Check if an action has exceeded its rate limit.
     *
     * @param string $key Unique identifier for the action/user/IP
     * @param int $max Maximum number of attempts
     * @param int $decay Seconds before the bucket resets
     * @return bool True if allowed, false if rate limited
     */
    public function rateLimit(string $key, int $max, int $decay): bool;
}
