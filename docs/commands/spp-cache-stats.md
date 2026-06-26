# NAME
spp cache:stats - Display cache driver statistics

# SYNOPSIS
`php spp.php cache:stats [--app=appname]`

# PURPOSE
Dumps low-level health, memory, and hit/miss diagnostic statistics natively supported by the actively bound cache driver to the terminal for debugging purposes.

# OPTIONS AVAILABLE
- `--app=<appname>` : Set the application context to probe the specific cache instance bound to that application. Defaults to `default`.

# UNDER THE HOOD ACTIVITY
Initializes the cache driver via `\SPP\Cache::driver()` inside the scoped `Scheduler::withContext`. It extracts the absolute class name of the driver (e.g. `SPP\Cache\RedisDriver`) and reports it. It then checks if the active driver instance implements a `stats()` method (`method_exists`). If available, it invokes it and passes the resulting array to `print_r()`. For advanced drivers like Redis, this yields memory consumption, connected clients, and hit/miss ratios mapped from native `INFO` commands. If the driver lacks the method (like simple file stores), it gracefully informs the user.

# EXAMPLES
View default cache driver stats:
`php spp.php cache:stats`
