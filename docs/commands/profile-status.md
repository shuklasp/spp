# NAME
`profile:status` - Check if the performance profiler is running/enabled

# SYNOPSIS
`php spp.php profile:status`

# PURPOSE
The `profile:status` command is used to quickly determine the health and operational status of the `SPPProfile` module within the framework. It checks whether the application's performance profiler is currently active and monitoring execution traces.

# OPTIONS AVAILABLE
This command does not accept any specific options or arguments.

# UNDER THE HOOD ACTIVITY
The command performs a lightweight runtime check using PHP's `class_exists()` function. It specifically looks for the `\SPPMod\SPPProfile\SPPProfile` class definition in the current execution environment. 

If the class is found in memory, the command infers that the profiler module has been installed, loaded, and initialized properly by the framework. It then prints out a confirmation that the module is `ACTIVE` and is currently "Monitoring performance traces". Conversely, if the class is not found, it outputs that the module is `NOT ACTIVE`, indicating that profiling is either uninstalled or disabled in the environment configurations.

# EXAMPLES
Check the status of the performance profiler:
```bash
php spp.php profile:status
```
