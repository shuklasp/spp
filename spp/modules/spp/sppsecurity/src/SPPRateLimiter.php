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

        // Fallback to local memory (only works for current request) if no cache
        static $localCache = [];
        
        if (!isset($localCache[$cacheKey])) {
            $localCache[$cacheKey] = [
                'hits' => 0,
                'expires' => time() + $decay
            ];
        }

        if (time() > $localCache[$cacheKey]['expires']) {
            $localCache[$cacheKey] = [
                'hits' => 1,
                'expires' => time() + $decay
            ];
            return true;
        }

        if ($localCache[$cacheKey]['hits'] >= $max) {
            return false;
        }

        $localCache[$cacheKey]['hits']++;
        return true;
    }
}
