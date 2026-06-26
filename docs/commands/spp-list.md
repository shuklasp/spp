# NAME

`spp list` - Lists all discovered SPP CLI commands

# SYNOPSIS

`php spp.php list`

# PURPOSE

The `list` command serves as the primary index for the SPP CLI. It outputs a formatted inventory of all commands that have been discovered and registered by the framework's CLI bootstrap process.

# OPTIONS AVAILABLE

This command requires no options or arguments.

# UNDER THE HOOD ACTIVITY

The `ListCommand` retrieves the associative array of instantiated command objects by accessing `\SPP\Registry::get('CLI_COMMANDS')`. The framework populates this registry array dynamically during the startup boot cycle by scanning and auto-loading classes from `SPP\CLI\Commands`.
The command uses `ksort()` to alphabetize the commands by their registered string name. It then iterates through the sorted array, using `str_pad` on the command name to enforce a 25-character width column, followed by printing the command's registered description via the `$cmd->getDescription()` method.

# EXAMPLES

**View all available commands:**
```bash
php spp.php list
```
