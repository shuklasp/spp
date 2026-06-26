# NAME

`app:list`

# SYNOPSIS

`php spp.php app:list`

# PURPOSE

Scans the system configuration and directories to display a comprehensive list of all registered and discovered SPP applications.

# OPTIONS AVAILABLE

This command accepts no specific options.

# UNDER THE HOOD ACTIVITY

The command attempts to resolve and parse the framework configuration file at `SPP_BASE_DIR . '/etc/global-settings.yml'` via `Symfony\Component\Yaml\Yaml::parseFile()`. It extracts the `apps` dictionary, which serves as the formal registry. 

To ensure complete visibility (even for unregistered apps), the command then scans the filesystem directory `SPP_APP_DIR . '/spp/etc/apps'` using `scandir()`. It iterates over the results, and if a valid directory is found that does not exist in the YAML registry keys, it appends it to the internal list of applications. 

With the complete array of application names assembled, it iterates over them to cross-reference metadata. It extracts properties like `type` (defaulting to 'native'), `base_url` (defaulting to `/appname`), and `table_prefix`. Furthermore, it evaluates the global `base_app` configuration key, appending a `[BASE]` tag to the application name that matches it. The final output is formatted into a fixed-width ASCII table using PHP's `str_pad()` function and echoed to standard output.

# EXAMPLES

List all applications:
```bash
php spp.php app:list
```
