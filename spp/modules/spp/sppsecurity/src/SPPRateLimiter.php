<?php
namespace SPPMod\Sppsecurity;

class SPPRateLimiter {
    /**
     * Token bucket style rate limiter (simplified using SPP Cache if available).
     * Falls back to a basic file or session implementation if cache isn't ready.
     */
    public function check(string $key, int $max, int $decay): bool {
        $cacheKey = "ratelimit:" . md5($key);
        
        // Try to use the core Cache facade if initialized
        if (class_exists('\SPP\Cache')) {
            $hits = (int)\SPP\Cache::get($cacheKey);
            if ($hits >= $max) {
                return false;
            }
            \SPP\Cache::set($cacheKey, $hits + 1, $decay);
            return true;
        }

        // Fallback to session if no cache
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION[$cacheKey])) {
            $_SESSION[$cacheKey] = [
                'hits' => 0,
                'expires' => time() + $decay
            ];
        }

        if (time() > $_SESSION[$cacheKey]['expires']) {
            $_SESSION[$cacheKey] = [
                'hits' => 1,
                'expires' => time() + $decay
            ];
            return true;
        }

        if ($_SESSION[$cacheKey]['hits'] >= $max) {
            return false;
        }

        $_SESSION[$cacheKey]['hits']++;
        return true;
    }
}
