# NAME

`schedule:run` - Run all scheduled cron tasks declared by active modules.

# SYNOPSIS

`php spp schedule:run`

# PURPOSE

The `schedule:run` command is responsible for executing the SPP Framework's internal scheduler. It aggregates cron tasks that are declared by various active modules and executes them sequentially. This is typically invoked via a system cron job to handle periodic background tasks across the application.

# OPTIONS AVAILABLE

This command accepts no explicit options or arguments.

# UNDER THE HOOD ACTIVITY

When `schedule:run` is executed, the following sequence occurs under the hood:
1. **Module Initialization**: It invokes `\SPP\Module::loadAllModules()` to parse and load all enabled modules within the SPP framework.
2. **Registry Retrieval**: It retrieves the instantiated module objects from the global registry via the `__modobj` key.
3. **Scheduler Instantiation**: An instance of `\SPP\Cron\Scheduler` is created to collect tasks.
4. **Task Gathering**: The command iterates over every loaded module. If a module has an attached `ServiceProvider` and that provider implements a `schedule()` method, the command calls it, passing the `$scheduler` instance. Modules use this hook to register their specific commands (e.g., `$scheduler->call(...)`).
5. **Execution**: Finally, `\SPP\Cron\Scheduler::run()` is invoked, triggering the execution stack of all gathered tasks based on their respective timing constraints.
6. **Output**: Status updates are echoed to STDOUT, detailing the number of modules tasks were gathered from and confirming execution completion.

# EXAMPLES

**Run the scheduler manually:**
```bash
php spp schedule:run
```

**Typical crontab entry (run every minute):**
```cron
* * * * * cd /path/to/project && php spp schedule:run >> /dev/null 2>&1
```
