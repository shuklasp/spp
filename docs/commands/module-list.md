# NAME
`module:list` - Discovers and tabulates active kernel framework modules

# SYNOPSIS
`php spp.php module:list`

# PURPOSE
Performs a rapid filesystem discovery to list all enterprise modules physically present within the framework's core module directory.

# OPTIONS AVAILABLE
No options required.

# UNDER THE HOOD ACTIVITY
When executed, this command does not query the database or the compiled framework registry. Instead, it performs a raw filesystem traversal. It specifically scans the directory path defined by `SPP_APP_DIR . '/spp/modules/spp'`.
Using PHP's `scandir()` function, it reads the contents of this directory, filtering out the standard `.` and `..` navigational artifacts. It validates that each discovered entity is a valid directory via `is_dir()`. For every valid directory found, it outputs a formatted string to standard output, identifying the directory name as a Module Context. Due to its direct filesystem approach, it serves as a raw diagnostic tool to verify the physical presence of module source code, bypassing potential logical errors in the framework's configuration or caching layers.

# EXAMPLES
- `php spp.php module:list` - Lists all directories recognized as modules.
