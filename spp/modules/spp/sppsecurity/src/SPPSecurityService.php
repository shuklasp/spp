<?php
namespace SPPMod\Sppsecurity;

use SPP\Core\SPPSecurityProvider;

/**
 * Class SPPSecurityService
 * 
 * Main entry point for the security module.
 */
class SPPSecurityService implements SPPSecurityProvider {
    
    private $csrf;
    private $sanitizer;
    private $rateLimiter;

    public function __construct() {
        $this->csrf = new SPPCsrf();
        $this->sanitizer = new SPPSanitizer();
        $this->rateLimiter = new SPPRateLimiter();
    }

    public function generateCsrfToken(): string {
        return $this->csrf->generate();
    }

    public function validateCsrfToken(string $token): bool {
        return $this->csrf->validate($token);
    }

    public function sanitize(string $input, string $context = 'html'): string {
        return $this->sanitizer->sanitize($input, $context);
    }

    public function rateLimit(string $key, int $max, int $decay): bool {
        return $this->rateLimiter->check($key, $max, $decay);
    }
}
