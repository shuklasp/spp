# NAME
spp cache:clear - Clear the application file/redis cache

# SYNOPSIS
`php spp.php cache:clear [--app=appname]`

# PURPOSE
Flushes the active cache driver (such as file system, Redis, or Memcached) completely, purging all stored application data segments for the designated context.

# OPTIONS AVAILABLE
- `--app=<appname>` : The application context to execute within. Defaults to `default`. The context ensures only the relevant isolated cache store is cleared if prefixing or separate instances are configured.

# UNDER THE HOOD ACTIVITY
The command wraps its execution inside `\SPP\Scheduler::withContext()` to bind the environment cleanly. It then statically invokes `\SPP\Cache::clear()`. Under the hood, the `Cache` facade resolves the currently bound driver (e.g. `FileCacheDriver` or `RedisCacheDriver`).
- For file caches, this typically translates to wiping the `var/cache` payload directories.
- For Redis, it issues a `FLUSHDB` or iteratively deletes keys matching the configured context prefix.
Execution success or failure booleans bubble up and are dumped directly to standard output. Exceptions thrown by misconfigured storage endpoints are caught and printed safely.

# EXAMPLES
Clear the default cache:
`php spp.php cache:clear`

Clear a specific application's cache:
`php spp.php cache:clear --app=storefront`
