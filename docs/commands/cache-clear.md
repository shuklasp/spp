# NAME
**cache:clear** - Clear the entire SPP Cache directory

# SYNOPSIS
`php spp.php cache:clear`

# PURPOSE
Flushes all cached data stored by the application. This is typically used after deploying new code, altering configurations, or when system memory needs to be purged to eliminate stale views and records.

# OPTIONS AVAILABLE
This command takes no arguments or options.

# UNDER THE HOOD ACTIVITY
The command retrieves the singleton instance of the caching engine by calling `\SPP\Cache::getInstance()`. It then invokes the `flush()` method on this instance. The `flush()` method is responsible for invalidating and purging all cache pools, which natively targets the filesystem cache directory or external data stores (like Redis or Memcached), depending on the active cache configuration. Finally, it outputs a success message upon completion.

# EXAMPLES
Clear the application cache:
`php spp.php cache:clear`
