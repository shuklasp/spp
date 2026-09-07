<?php

namespace SPP\Core;

/**
 * Class Url
 * 
 * Centralized framework utility for URL generation, validation, normalization,
 * and routing resolution across the SPP ecosystem.
 */
class Url
{
    /**
     * Recognized absolute protocols/schemes that should not be mangled with base URLs.
     */
    private const ABSOLUTE_SCHEMES = [
        'http://',
        'https://',
        'ftp://',
        'mailto:',
        'tel:',
        'sms:',
        'javascript:',
        'data:',
        '#',
        '//'
    ];

    /**
     * Generate an application or external URL.
     *
     * If $path is already an external URL or absolute scheme, it will be returned intact
     * with any optional $queryParams appended.
     *
     * @param string $path Target path, route, or external URL
     * @param string|null $appName Specific application context (defaults to active scheduler context)
     * @param array $queryParams Optional query parameters to append
     * @return string Fully qualified or routed URL
     */
    public static function to(string $path = '', ?string $appName = null, array $queryParams = []): string
    {
        $path = trim($path);

        // 1. If path is already an absolute protocol or external URL, return intact
        if (self::hasAbsoluteScheme($path)) {
            return self::appendQueryParams($path, $queryParams);
        }

        // 2. Resolve internal base URL
        $baseUrl = \SPP\App::getBaseUrl($appName);
        $cleanPath = ltrim($path, '/');

        if ($cleanPath === '') {
            $url = $baseUrl;
        } elseif (\SPP\App::hasUrlRewriting()) {
            $url = rtrim($baseUrl, '/') . '/' . $cleanPath;
        } else {
            $url = rtrim($baseUrl, '/') . '/?q=' . $cleanPath;
        }

        return self::appendQueryParams($url, $queryParams);
    }

    /**
     * Normalizes an external URL to ensure a valid transport scheme (defaulting to https://).
     * Prevents modern web browsers from misinterpreting raw domains (e.g. 'github.com/shuklasp/spp')
     * as relative internal paths under the active application route.
     *
     * @param string|null $url Input URL or domain string
     * @param string $defaultScheme Protocol to prepend if omitted ('https' or 'http')
     * @return string Normalized external URL, or empty string if input is empty
     */
    public static function external(?string $url, string $defaultScheme = 'https'): string
    {
        if ($url === null) {
            return '';
        }

        $trimmed = trim($url);
        if ($trimmed === '' || $trimmed === '#') {
            return $trimmed;
        }

        // If it starts with an absolute scheme or protocol-relative '//', return as is
        if (self::hasAbsoluteScheme($trimmed)) {
            return $trimmed;
        }

        // If it starts with a root slash '/', it is a site-root relative URL, not external
        if (str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        // Prepend default scheme
        $scheme = rtrim($defaultScheme, ':/') . '://';
        return $scheme . $trimmed;
    }

    /**
     * Alias for external() with semantic name.
     */
    public static function normalize(?string $url, string $defaultScheme = 'https'): string
    {
        return self::external($url, $defaultScheme);
    }

    /**
     * Determine if a given string represents an external URL.
     *
     * @param string $url
     * @return bool
     */
    public static function isExternal(string $url): bool
    {
        $url = trim($url);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            $host = parse_url($url, PHP_URL_HOST);
            $currentHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($host && $currentHost && strcasecmp($host, $currentHost) === 0) {
                return false;
            }
            return true;
        }

        // Check if string matches domain pattern without scheme (e.g., github.com/foo)
        if (preg_match('#^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}(/.*)?$#', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a URL has an absolute scheme or special prefix.
     */
    public static function hasAbsoluteScheme(string $url): bool
    {
        $trimmed = trim($url);
        foreach (self::ABSOLUTE_SCHEMES as $scheme) {
            if (str_starts_with(strtolower($trimmed), $scheme)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve the current full URL including protocol, host, port, and query string.
     *
     * @param bool $withQuery
     * @return string
     */
    public static function current(bool $withQuery = true): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (!$withQuery) {
            $parts = explode('?', $uri, 2);
            $uri = $parts[0];
        }

        return $protocol . $host . $uri;
    }

    /**
     * Retrieve the HTTP Referer or fallback URL if not available.
     *
     * @param string|null $fallback
     * @return string
     */
    public static function previous(?string $fallback = null): string
    {
        return $_SERVER['HTTP_REFERER'] ?? ($fallback !== null ? $fallback : self::to(''));
    }

    /**
     * Append query parameters to any URL string cleanly without duplicate separators.
     *
     * @param string $url
     * @param array $queryParams
     * @return string
     */
    public static function appendQueryParams(string $url, array $queryParams): string
    {
        if (empty($queryParams)) {
            return $url;
        }

        $queryString = http_build_query($queryParams);
        $separator = (strpos($url, '?') !== false) ? '&' : '?';

        return $url . $separator . $queryString;
    }

    /**
     * Validate if a string is a syntactically valid URL.
     *
     * @param string $url
     * @return bool
     */
    public static function isValid(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL);
    }
}

// Register global class alias for convenience: \SPP\Url
if (!class_exists('SPP\\Url', false)) {
    class_alias(Url::class, 'SPP\\Url');
}
