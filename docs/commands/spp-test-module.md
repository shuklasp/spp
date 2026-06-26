# NAME

`test:module` - Run PHPUnit tests for an isolated module

# SYNOPSIS

`php spp.php test:module <modulename>`

# PURPOSE

Executes the PHPUnit test suite for a specific SPP Framework module, isolating tests strictly to the specified module instead of running the entire application test suite.

# OPTIONS AVAILABLE

- `<modulename>`: The exact name of the registered SPP module. (Required)

# UNDER THE HOOD ACTIVITY

The command begins by asserting that a module name was provided in the arguments. It loads all system modules via `\SPP\Module::loadAllModules()` and attempts to retrieve the target module using `\SPP\Module::getModule($moduleName)`. If the module is not found or is inactive, execution halts.

It resolves the module's absolute directory path and appends `/tests` to find the test suite directory. Next, it looks for the `phpunit` executable inside `SPP_BASE_DIR . '/vendor/bin/phpunit'`. It accounts for Windows environments by also checking for `phpunit.bat` if the extensionless binary isn't found. 

Once resolved, the command executes the test suite synchronously via `passthru()`, passing output directly to STDOUT in real-time. Finally, based on the exit code returned by PHPUnit, it outputs a formatted success or error message.

# EXAMPLES

**Run tests for the "sppauth" module:**
```bash
php spp.php test:module sppauth
```
