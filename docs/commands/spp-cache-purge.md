# NAME
spp cache:purge - Purge cache tags or URLs from the reverse proxy (Varnish/CDN)

# SYNOPSIS
`php spp.php cache:purge [--tags=tag1,tag2] [--url=/path]`

# PURPOSE
Acts as a frontend edge-cache invalidator. It sends explicit `PURGE` or `BAN` HTTP HTTP requests to a configured upstream reverse proxy (like Varnish) or Content Delivery Network, allowing fine-grained eviction of cached HTTP responses by explicit URI or assigned metadata tags.

# OPTIONS AVAILABLE
- `--url=<path>` : The exact URI path to evict from the reverse proxy via an HTTP `PURGE` request.
- `--tags=<tag1,tag2>` : A comma-separated list of cache tags to invalidate via an HTTP `BAN` request.

# UNDER THE HOOD ACTIVITY
1. **Target Resolution:** It probes the `SPPConfig` core registry for the `reverse_proxy_url` directive. If undefined, it falls back gracefully to `http://127.0.0.1:80` (Standard local Varnish deployment).
2. **URL Purging:** If the `--url` flag is provided, the script uses PHP's `curl` extension to build a raw request to the target combining the proxy URL and the path. It explicitly overrides the request verb via `CURLOPT_CUSTOMREQUEST, "PURGE"`. It executes the request and retrieves the HTTP response code to print to the console.
3. **Tag Banning:** If the `--tags` flag is provided, it targets the root proxy URL using `CURLOPT_CUSTOMREQUEST, "BAN"`. Crucially, it injects an `X-Cache-Tags: tag1,tag2` HTTP header. Varnish configurations listening for this header will intercept the BAN request and wipe all cached objects internally mapped to those tags using smart ban-lurker threads.

# EXAMPLES
Purge a specific page URL from Varnish:
`php spp.php cache:purge --url=/products/widget-pro`

Ban multiple data-linked tags globally:
`php spp.php cache:purge --tags=product_10,catalog_update`
