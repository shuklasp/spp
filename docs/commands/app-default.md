# NAME

`app:default`

# SYNOPSIS

`php spp.php app:default [--set=<app_name>]`

# PURPOSE

View or persistently set the default global CLI application context. Setting this determines which application environment is bootstrapped when executing commands that depend on application-level context without explicit flags.

# OPTIONS AVAILABLE

- `--set=<app_name>` : Update the settings to make the specified application the default context for future CLI executions.

# UNDER THE HOOD ACTIVITY

The command linearly scans the incoming CLI `$args` array to detect the `--set=` parameter, extracting the trailing substring value if present. It defines the canonical path to the CLI configuration at `SPP_APP_DIR . '/spp/etc/cli-settings.yml'`.

If the `--set` option is provided, the command attempts to load the existing CLI settings using `Symfony\Component\Yaml\Yaml::parseFile()`. If the file does not exist, it initializes an empty array. It then assigns the provided application name to the `['default_app']` array key. The modified array is immediately serialized back to YAML format via `Yaml::dump()` and written to the `cli-settings.yml` file using `file_put_contents()`. 

If the `--set` option is omitted, the command performs a read-only operation: parsing the YAML file (or defaulting to an empty array) and echoing the value of the `default_app` key, falling back to the string `'default'` if the key remains unconfigured.

# EXAMPLES

Check the current default CLI application context:
```bash
php spp.php app:default
```

Set the default CLI context to 'admin_panel':
```bash
php spp.php app:default --set=admin_panel
```
