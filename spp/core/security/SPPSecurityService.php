<?php
namespace SPP\Core\Security;

use SPP\Core\SPPSecurityProvider;

/**
 * Class SPPSecurityService
 * 
 * Main entry point for the core security subsystem.
 */
class SPPSecurityService implements SPPSecurityProvider
{

    private $csrf;
    private $sanitizer;
    private $rateLimiter;

    public function __construct(
        ?SPPCsrf $csrf = null,
        ?SPPSanitizer $sanitizer = null,
        ?SPPRateLimiter $rateLimiter = null
    ) {
        $app = class_exists('\SPP\App') ? \SPP\App::getInstance() : null;
        $this->csrf = $csrf ?? ($app ? $app->make(SPPCsrf::class) : new SPPCsrf());
        $this->sanitizer = $sanitizer ?? ($app ? $app->make(SPPSanitizer::class) : new SPPSanitizer());
        $this->rateLimiter = $rateLimiter ?? ($app ? $app->make(SPPRateLimiter::class) : new SPPRateLimiter());
    }

    public function generateCsrfToken(): string
    {
        return $this->csrf->generate();
    }

    public function validateCsrfToken(string $token): bool
    {
        return $this->csrf->validate($token);
    }

    public function sanitize(string $input, string $context = 'html'): string
    {
        return $this->sanitizer->sanitize($input, $context);
    }

    public function rateLimit(string $key, int $max, int $decay): bool
    {
        return $this->rateLimiter->check($key, $max, $decay);
    }
}
