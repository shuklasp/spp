<?php
namespace SPP\Middleware;

/**
 * CacheControlMiddleware
 * 
 * Sets Cache-Control, ETag, and Surrogate-Key headers for edge caching (Varnish/CDN).
 */
class CacheControlMiddleware
{
    /**
     * Handle the response headers before output.
     *
     * @param string $content Output content (to generate ETag).
     * @param array $cacheTags Array of cache tags associated with the page.
     * @param int $maxAge Max age in seconds.
     */
    public static function handle(string $content, array $cacheTags = [], int $maxAge = 3600): void
    {
        if (headers_sent() || php_sapi_name() === 'cli') {
            return;
        }

        // Cache-Control
        header("Cache-Control: public, max-age={$maxAge}, s-maxage={$maxAge}");

        // ETag
        $etag = md5($content);
        header("ETag: \"{$etag}\"");

        // Surrogate-Key for Fastly / Varnish (comma-separated tags)
        if (!empty($cacheTags)) {
            $tags = implode(' ', $cacheTags);
            header("Surrogate-Key: {$tags}");
            header("X-Cache-Tags: {$tags}"); // For custom Varnish setups
        }
    }
}
