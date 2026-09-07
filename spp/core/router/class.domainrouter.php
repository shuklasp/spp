<?php

namespace SPP\Core\Router;

use Symfony\Component\Yaml\Yaml;

/**
 * Class DomainRouter
 *
 * Provides multi-tenant virtual host and domain routing for the SPP framework.
 * Maps incoming HTTP Host headers (or custom domain patterns) to application contexts,
 * allowing single-engine multi-tenancy and dedicated domain isolation.
 *
 * Supports exact domain matching ('docs.example.com') and wildcard domain patterns ('*.tenant.example.com').
 *
 * @package SPP\Core\Router
 * @author Satya Prakash Shukla
 */
class DomainRouter
{
    /** @var array<string,string> Domain pattern to application context mapping */
    private static array $domainMap = [];

    /** @var bool Whether domains config has been loaded from file */
    private static bool $loaded = false;

    /** @var string|null The host matched during the current request lifecycle */
    private static ?string $matchedHost = null;

    /** @var string|null The application context resolved from the domain */
    private static ?string $matchedContext = null;

    /**
     * Load domain configurations from etc/domains.yml.
     */
    public static function loadConfig(?string $customPath = null): void
    {
        if (self::$loaded && $customPath === null) {
            return;
        }

        $searchPaths = [];
        if ($customPath !== null) {
            $searchPaths[] = $customPath;
        }

        if (defined('SPP_APP_DIR')) {
            $searchPaths[] = SPP_APP_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.yml';
        }
        if (defined('SPP_BASE_DIR')) {
            $searchPaths[] = SPP_BASE_DIR . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'domains.yml';
        }

        foreach ($searchPaths as $file) {
            if (file_exists($file)) {
                try {
                    $parsed = Yaml::parseFile($file);
                    if (is_array($parsed) && !empty($parsed['domains']) && is_array($parsed['domains'])) {
                        foreach ($parsed['domains'] as $pattern => $app) {
                            self::$domainMap[strtolower(trim($pattern))] = trim($app);
                        }
                    }
                } catch (\Throwable $e) {
                    error_log("[DomainRouter] Failed to parse domains configuration ({$file}): " . $e->getMessage());
                }
                break;
            }
        }

        self::$loaded = true;
    }

    /**
     * Register a domain pattern mapping programmatically at runtime.
     *
     * @param string $pattern Domain pattern (e.g. 'docs.example.com' or '*.client.com')
     * @param string $app Target application context
     */
    public static function registerDomain(string $pattern, string $app): void
    {
        self::$domainMap[strtolower(trim($pattern))] = trim($app);
    }

    /**
     * Resolve an HTTP Host to its corresponding application context.
     *
     * @param string|null $host Optional host string (defaults to $_SERVER['HTTP_HOST'])
     * @return string|null Resolved application context or null if unmapped
     */
    public static function resolve(?string $host = null): ?string
    {
        self::loadConfig();

        if ($host === null) {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        }

        if ($host === '') {
            return null;
        }

        // Strip port numbers if present (e.g. localhost:8080 -> localhost)
        $cleanHost = strtolower(trim(explode(':', $host)[0]));

        // 1. Direct exact match check
        if (isset(self::$domainMap[$cleanHost])) {
            self::$matchedHost = $cleanHost;
            self::$matchedContext = self::$domainMap[$cleanHost];
            return self::$matchedContext;
        }

        // 2. Wildcard pattern matching (e.g. *.tenant.domain.com)
        foreach (self::$domainMap as $pattern => $app) {
            if (str_contains($pattern, '*')) {
                if (fnmatch($pattern, $cleanHost, FNM_CASEFOLD)) {
                    self::$matchedHost = $cleanHost;
                    self::$matchedContext = $app;
                    return $app;
                }
            }
        }

        return null;
    }

    /**
     * Check if the current request was resolved via domain virtual host mapping.
     */
    public static function isDomainMatched(): bool
    {
        return self::$matchedContext !== null;
    }

    /**
     * Get the matched host for the current request.
     */
    public static function getMatchedHost(): ?string
    {
        return self::$matchedHost;
    }

    /**
     * Get the resolved context for the current request.
     */
    public static function getMatchedContext(): ?string
    {
        return self::$matchedContext;
    }

    /**
     * Return all registered domain mappings.
     *
     * @return array<string,string>
     */
    public static function getAllDomains(): array
    {
        self::loadConfig();
        return self::$domainMap;
    }

    /**
     * Reset router state (primarily for testing and worker daemon isolation).
     */
    public static function reset(): void
    {
        self::$domainMap = [];
        self::$loaded = false;
        self::$matchedHost = null;
        self::$matchedContext = null;
    }
}

// Global namespace alias for seamless access
if (!class_exists('\SPP\DomainRouter', false)) {
    class_alias(\SPP\Core\Router\DomainRouter::class, '\SPP\DomainRouter');
}
