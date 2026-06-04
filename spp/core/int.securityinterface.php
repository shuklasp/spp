<?php

namespace SPP;

/**
 * Interface SPPSecurityProvider
 * Contract for kernel-level security operations to be fulfilled by a module.
 */
interface SPPSecurityProvider
{
    /**
     * Generate a new CSRF token.
     * @return string
     */
    public function generateCsrfToken(): string;

    /**
     * Validate a provided CSRF token against the stored session token.
     * @param string $token
     * @return bool
     */
    public function validateCsrfToken(string $token): bool;

    /**
     * Sanitize input data based on context.
     * @param string $input
     * @param string $context (e.g. 'html', 'attr', 'js')
     * @return string
     */
    public function sanitize(string $input, string $context = 'html'): string;

    /**
     * Limit the rate of an action based on a key.
     * @param string $key Identifier for the rate limit
     * @param int $max Maximum allowed hits
     * @param int $decay Seconds before the limit resets
     * @return bool True if allowed, False if limit exceeded
     */
    public function rateLimit(string $key, int $max, int $decay): bool;
}
