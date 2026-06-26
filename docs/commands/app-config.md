# NAME

`app:config`

# SYNOPSIS

`php spp.php app:config <app_name> [--base_url=...] [--table_prefix=...]`

# PURPOSE

Dynamically configures application-specific settings, such as the `base_url` or `table_prefix`, by directly modifying the global YAML settings definition.

# OPTIONS AVAILABLE

- `<app_name>` : (Required Positional Argument) The registered name of the target application to configure.
- `--base_url=...` : Set the fully qualified Base URL for the specified application.
- `--table_prefix=...` : Define a database table prefix specific to the target application context.

# UNDER THE HOOD ACTIVITY

The command immediately invokes its inherited `getArgument($args, 0)` method to isolate the `<app_name>`. Without it, execution halts with usage instructions. It defines the path to the framework's primary configuration file at `SPP_BASE_DIR . '/etc/global-settings.yml'`. If this file is absent from the filesystem, it aborts.

Using the `Symfony\Component\Yaml\Yaml::parseFile()` utility, it reads the entire YAML file into an associative PHP array. It verifies that the specified `<app_name>` exists under the `['apps']` configuration node. If validation passes, a reference to the specific app's array node is established. 

The command then extracts the `--base_url` and `--table_prefix` flags using `getOption()`. If either value is present, the target array node is mutated in memory, and an `$updated` boolean flag is toggled to true. When updates occur, the entire associative array is re-serialized back into YAML format via `Yaml::dump()` (utilizing an inline depth of 10 and indentation of 2 spaces) and written destructively back to `global-settings.yml` using `file_put_contents()`. If no flags are provided, the command harmlessly dumps the existing array node to the console via `print_r()`.

# EXAMPLES

Set the base URL and table prefix for the 'frontend' application:
```bash
php spp.php app:config frontend --base_url=https://www.example.com --table_prefix=fr_
```

View the current configuration for the 'api' application:
```bash
php spp.php app:config api
```
