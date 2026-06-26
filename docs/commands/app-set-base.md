# NAME

`app:set-base`

# SYNOPSIS

`php spp.php app:set-base <app_name>`

# PURPOSE

Modifies the global configuration to designate a specific application as the primary or "base" application for routing and context purposes.

# OPTIONS AVAILABLE

- `<app_name>` : (Required Positional Argument) The registered name of the application to promote to the base context.

# UNDER THE HOOD ACTIVITY

Execution begins by asserting the presence of the `<app_name>` positional argument at index 2 of the CLI argument array. If missing, it outputs the expected usage. It defines the path to the configuration matrix at `SPP_BASE_DIR . '/etc/global-settings.yml'` and aborts if the file cannot be located.

The command parses the YAML payload into memory utilizing `Symfony\Component\Yaml\Yaml::parseFile()`. It conducts a validation check to ensure the provided `<app_name>` actually exists within the `['apps']` configuration key. If the application is unregistered, it throws an error and exits.

Upon successful validation, the top-level configuration key `['base_app']` is overridden with the newly designated application name. The modified array structure is serialized via `Yaml::dump()` (configured with a depth parameter of 10 and 2 spaces indentation) and persisted directly to the `global-settings.yml` file using `file_put_contents()`, making the state change immediate across the framework.

# EXAMPLES

Set the 'frontend' application as the global base:
```bash
php spp.php app:set-base frontend
```
