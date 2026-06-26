# NAME
`module:setting:update` - Update a configuration setting for a specific module

# SYNOPSIS
`php spp.php module:setting:update <modname> <key> <value>`

# PURPOSE
Provides a direct CLI interface to mutate a specific configuration variable within a module's settings registry, bypassing web-based admin interfaces.

# OPTIONS AVAILABLE
- `<modname>` : The name of the module owning the setting.
- `<key>` : The specific configuration key to update.
- `<value>` : The new value to assign to the key.

# UNDER THE HOOD ACTIVITY
This command acts as a strict mutator for module state. After validating that all three required arguments (module name, configuration key, and new value) are present, it directly invokes the core framework API: `\SPP\Module::setConfig($key, $val, $modname)`.
Under the hood, the `setConfig` method performs several critical operations. It first references the module's setting definition schema to cast the incoming string value from the CLI into the appropriate native PHP data type (e.g., boolean, integer, array). It then validates the data. Once validated, the framework persists the new value—typically writing it to a centralized `sys_config` database table or a configuration file—and automatically flushes the relevant application cache so the change takes effect immediately across all runtime contexts. The command catches any schema validation exceptions or database write errors and outputs a color-coded success or failure message to the terminal.

# EXAMPLES
- `php spp.php module:setting:update smtp port 587` - Updates the `port` setting in the `smtp` module to `587`.
- `php spp.php module:setting:update core debug_mode true` - Updates the `debug_mode` key in the `core` module.
