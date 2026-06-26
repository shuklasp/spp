# NAME
spp cron:list - List all registered scheduled tasks

# SYNOPSIS
`php spp.php cron:list [--app=appname]`

# PURPOSE
Inspects the framework's internal scheduler and outputs all programmatic cron jobs and time expressions natively.

# OPTIONS AVAILABLE
- `--app=<appname>` : Set the application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY
Verifies that `\SPP\Cron\Scheduler` exists. It then utilizes PHP's `\ReflectionClass` and `\ReflectionProperty` mechanisms to forcefully bypass internal visibility protections, exposing the protected static `$tasks` array. It iterates the array structure to pull human-readable cron time expressions and prints them chronologically.

# EXAMPLES
`php spp.php cron:list`
