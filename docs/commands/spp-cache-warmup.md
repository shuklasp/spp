# NAME
spp cache:warmup - Warm up common application caches

# SYNOPSIS
`php spp.php cache:warmup [--app=appname]`

# PURPOSE
Triggers preemptive generation and compilation of heavily utilized application assets (like view templates, routing maps, or dependency trees) to ensure rapid response times on initial web requests following a deployment or cache clearance.

# OPTIONS AVAILABLE
- `--app=<appname>` : Designate the app context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY
It initializes the cache environment safely. It logs the active cache driver. Currently, its primary functional mechanic is detecting the presence of the `\SPPMod\SPPBlade\SPPBlade` module. If the Blade engine is loaded, the warmup command artificially triggers an engine boot cycle which compiles available `.blade.php` files into raw PHP files located in `var/cache/`. This circumvents the on-the-fly compilation delay normally experienced by the first visitor. Extensible try/catch blocks wrap the procedure to prevent compilation errors from crashing post-deployment CI pipelines.

# EXAMPLES
Warm up cache payloads:
`php spp.php cache:warmup --app=storefront`
