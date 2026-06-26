# NAME
`module:uninstall` - Uninstall a module (drops tracking but retains data tables)

# SYNOPSIS
`php spp.php module:uninstall <modulename>`

# PURPOSE
Safely uninstalls a module by executing its teardown routines and removing it from the active framework registry, while deliberately retaining core database tables to prevent accidental data loss.

# OPTIONS AVAILABLE
- `<modulename>` : The exact registry name of the module to uninstall.

# UNDER THE HOOD ACTIVITY
The command delegates the teardown process to `SPP\Core\ModuleInstaller::uninstall()`. When executed, the installer framework locates the specified module and attempts to invoke its `uninstall()` hook defined within its Service Provider.
This uninstallation hook typically removes scheduled cron jobs, unbinds specific event listeners, clears module-specific temporary files, and drops any volatile cache keys. Crucially, the standard SPP convention dictates that `uninstall` should *not* drop primary database tables containing user data; it only purges configuration tracking and framework integration points. 
Finally, the module's registration flag in the core tracker is removed or set to a fully uninstalled state, followed by a total framework cache purge. The CLI command monitors this process via a try-catch block and provides immediate visual feedback on the terminal regarding the success of the teardown.

# EXAMPLES
- `php spp.php module:uninstall legacy_ui` - Removes the `legacy_ui` module from the application registry.
