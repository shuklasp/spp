# NAME
`module:update` - Execute the update hook for a specific module

# SYNOPSIS
`php spp.php module:update <modulename> [--from=VERSION] [--to=VERSION]`

# PURPOSE
Triggers the specific, version-aware update logic for a module. This is used to apply schema alterations, data migrations, or configuration upgrades when transitioning a module between specific versions.

# OPTIONS AVAILABLE
- `<modulename>` : The name of the module to update.
- `--from=VERSION` : The version string the module is currently on. Defaults to `unknown`.
- `--to=VERSION` : The version string the module is upgrading to. Defaults to `latest`.

# UNDER THE HOOD ACTIVITY
To perform a precise update, the command first extracts the optional version parameters from the CLI arguments. It then forcefully loads the entire module ecosystem into memory using `\SPP\Module::loadAllModules()`. 
It fetches the specific module object via `getModule()`. If the module is found and active, the command introspects the module's registered `ServiceProvider` instance. It uses PHP's `method_exists()` function to verify if the Service Provider explicitly implements an `update()` method.
If the hook is present, the command executes `$provider->update($fromVersion, $toVersion)`. This method is entirely controlled by the module's author and is typically utilized to execute specialized database `ALTER TABLE` statements, migrate serialized data formats, or patch configuration keys between the specified versions. If the `update()` method throws any exceptions, the command catches them and dumps the error trace to standard output. If the method is absent, it safely skips execution and informs the user.

# EXAMPLES
- `php spp.php module:update auth` - Runs the generic update hook for the `auth` module.
- `php spp.php module:update commerce --from=1.0.2 --to=1.1.0` - Runs the update hook passing specific version boundaries.
