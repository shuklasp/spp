# NAME
spp cron:flush - Clear cron history and lock files

# SYNOPSIS
`php spp.php cron:flush [--app=appname]`

# PURPOSE
Troubleshooting utility that removes orphaned scheduler lock files, unfreezing cron job loops that stalled mid-execution.

# OPTIONS AVAILABLE
- `--app=<appname>` : Set the application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY
Resolves within the bound application context. Constructs a deterministic filesystem path targeting `SPP_APP_DIR/var/storage/temp/cron.lock`. Uses `file_exists()` to locate it, and if discovered, surgically deletes the semaphore using `unlink()`.

# EXAMPLES
`php spp.php cron:flush`
