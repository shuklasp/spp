# NAME
`module:setting:list` - List all settings for a given module

# SYNOPSIS
`php spp.php module:setting:list <modname>`

# PURPOSE
Extracts and tabulates the complete configuration schema for a specific module, displaying each setting's key, data type, currently configured value, and default value.

# OPTIONS AVAILABLE
- `<modname>` : The exact registry name of the module whose settings you wish to inspect.

# UNDER THE HOOD ACTIVITY
The command orchestrates an inspection of a module's internal configuration state. It begins by retrieving the module instance via `\SPP\Module::getModule($modname)`. It then invokes `$mod->getSettingsDefinition()`, a method that reads the module's manifest (`module.json` or defined Service Provider) to extract the structural schema of allowable settings, including expected data types and hardcoded default values.
If a schema exists, the command iterates over every defined configuration key. For each key, it performs a live lookup using `\SPP\Module::getConfig($key, $modname)` to retrieve the current, active value (which may be sourced from the database, environment variables, or cache). 
The data is then sanitized—complex or non-scalar values are safely converted to string representations via `json_encode()`—and aggregated into an array. Finally, it passes this normalized data to a CLI table rendering utility (`printTable()`), presenting a clean, formatted matrix of the module's configuration surface directly to the console.

# EXAMPLES
- `php spp.php module:setting:list smtp` - Displays all configurable settings, current values, and defaults for the `smtp` module.
