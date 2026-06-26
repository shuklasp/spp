# NAME
`module:install` - Install or upgrade a specific module or all active modules

# SYNOPSIS
`php spp.php module:install <modulename> [--all]`

# PURPOSE
Initializes and installs a new module, or upgrades an existing one. It triggers the module's installation routines, which may involve copying assets, setting up default configurations, or preparing database schemas.

# OPTIONS AVAILABLE
- `<modulename>` : The target module to install or upgrade. Required unless `--all` is provided.
- `--all` : A flag to recursively trigger the installation/upgrade routine for every currently active module in the system.

# UNDER THE HOOD ACTIVITY
The CLI arguments are parsed to determine whether a batch operation (`--all`) or a targeted installation is requested. 
If the `--all` flag is detected, the command delegates execution to `SPP\Core\ModuleInstaller::installAllActive()`. This method iterates through the framework's registry of active modules and systematically invokes the `install()` routine for each, aggregating the success states and error messages into a structured array which is then printed as a formatted list to the terminal.
If a specific module name is provided, `ModuleInstaller::install($moduleName)` is executed. Under the hood, the installer loads the target module's manifest, resolves its Service Provider, and executes its dedicated `install()` hook. This hook is typically responsible for publishing configuration files, registering scheduled cron tasks, seeding initial database tables, or linking public storage assets. The entire operation is shielded by a try-catch block to prevent a faulty module installation script from causing a fatal crash.

# EXAMPLES
- `php spp.php module:install auth` - Installs or upgrades the `auth` module.
- `php spp.php module:install --all` - Runs the installation routines for all active modules in the application.
