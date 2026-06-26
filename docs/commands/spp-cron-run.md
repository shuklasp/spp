# NAME
spp cron:run - Execute pending cron jobs manually

# SYNOPSIS
`php spp.php cron:run [--app=appname]`

# PURPOSE
Forces a synchronization cycle of the framework's internal cron scheduler evaluating and dispatching tasks manually. Usually hooked to system `crontab`.

# OPTIONS AVAILABLE
- `--app=<appname>` : Set the application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY
Binds application contexts and verifies the `Scheduler` namespace exists. Invokes the static entry point `\SPP\Cron\Scheduler::run()` directly. The scheduler dynamically matches all task expressions against the server clock, enforces local locking semaphores, and triggers callback pipelines immediately within the current thread blocking sequence.

# EXAMPLES
`php spp.php cron:run`
