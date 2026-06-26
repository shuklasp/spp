# NAME
`make:command` - Create a new CLI command class

# SYNOPSIS
`php spp.php make:command <name> [--app=appname] [--command=cmd:name]`

# PURPOSE
The `make:command` utility rapidly scaffolds a new PHP CLI command class that integrates seamlessly into the SPP task runner. It handles namespace resolution, class naming conventions, and generates the necessary base execution logic, ensuring the new command is immediately discoverable by the CLI runtime.

# OPTIONS AVAILABLE
- `<name>` (string, required): The target name of the class (e.g. `ClearCache`). If the word "Command" is omitted, it will automatically append it to the class name (e.g. `ClearCacheCommand`).
- `--app=<appname>` (string, optional): The execution context/app where this command should be bound. Affects namespace and target directories.
- `--command=<cmd:name>` (string, optional): Overrides the actual CLI execution signature (the string typed after `php spp.php`). Defaults to the lowercase version of `<name>`.

# UNDER THE HOOD ACTIVITY
When invoked, the command resolves the target directory mapping using `getTargetDir()` on the `commands` folder. It standardizes the class name string via `ucfirst()`. The command explicitly searches for the `--command=` flag in the arguments array to assign the `$name` property of the new command object. Finally, it delegates the actual file generation to `buildFromStub()`, merging the `namespace`, `className`, `commandName`, and a placeholder description into the predefined `command` stub file. The newly created command is automatically picked up via the `SPP\CLI\CommandManager` namespace auto-discovery mechanism.

# EXAMPLES
**1. Scaffold a database backup command:**
```bash
php spp.php make:command BackupDb --command=db:backup
```
