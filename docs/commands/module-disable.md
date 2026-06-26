# NAME
`module:disable` - Disable an SPP module

# SYNOPSIS
`php spp.php module:disable <modulename>`

# PURPOSE
Safely deactivates an installed SPP framework module, preventing its logic, hooks, and routes from being loaded during the application lifecycle, without deleting its data or source files.

# OPTIONS AVAILABLE
- `<modulename>` : The exact registry name of the module to disable.

# UNDER THE HOOD ACTIVITY
The command relies heavily on the core `SPP\Core\ModuleInstaller` class. When invoked with a valid module identifier, it calls the `setModuleStatus()` method, passing the state as `inactive`.
Under the hood, this operation locates the module's registration state (typically stored in a central configuration or database registry) and toggles its active flag. Additionally, deactivating a module inherently triggers a framework-wide cache invalidation and recompilation process, ensuring that the router, dependency injection container, and event dispatchers are purged of the disabled module's bindings. The command wraps this entire procedure in a try-catch block to gracefully handle filesystem permission errors or registry inconsistencies, outputting immediate feedback to standard out.

# EXAMPLES
- `php spp.php module:disable sppdb` - Disables the `sppdb` module.
