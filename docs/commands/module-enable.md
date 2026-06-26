# NAME
`module:enable` - Enable an SPP module

# SYNOPSIS
`php spp.php module:enable <modulename>`

# PURPOSE
Activates an installed, previously disabled SPP module, integrating its functionality, routes, and services back into the live framework ecosystem.

# OPTIONS AVAILABLE
- `<modulename>` : The exact registry name of the module to enable.

# UNDER THE HOOD ACTIVITY
The command utilizes the `SPP\Core\ModuleInstaller` class to mutate the framework's module registry. By invoking `setModuleStatus($moduleName, 'active')`, it updates the configuration tracker to mark the module as bootable.
Activating a module forces the framework to flush its compiled caches. On the next HTTP request or CLI invocation, the SPP kernel will scan the activated module's directory, parse its `module.json` manifest, auto-discover its Service Providers, and register its routes and event listeners. The command handles any underlying exceptions during the cache invalidation or registry write process, guaranteeing that the terminal output accurately reflects the success or failure of the activation request.

# EXAMPLES
- `php spp.php module:enable sppdb` - Enables the `sppdb` module.
