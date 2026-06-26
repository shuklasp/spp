# NAME

`ai:providers`

# SYNOPSIS

`php spp.php ai:providers [--app=<appname>]`

# PURPOSE

Lists all AI providers that have been successfully registered within the current application's SPPAI module configuration.

# OPTIONS AVAILABLE

- `--app=<appname>` : Set the SPP Application context to evaluate. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The command extracts the `--app` option from the arguments list to determine the target application context. Execution is encapsulated within `\SPP\Scheduler::withContext()` to guarantee the correct application environment variables and configurations are in place before fetching provider information.

Inside the callback, the `sppai` module is dynamically loaded and checked for availability. The script invokes the static method `\SPPMod\SPPAI\SPPAI::getRegistry()` to retrieve an associative array of all configured AI providers. If the registry is populated, the command iterates through the provider configurations, extracting the designated default `model` and the `active` boolean flag. It outputs the information in an aligned ASCII table using `str_pad()`, making it easy to identify which providers are properly configured and currently active within the application state.

# EXAMPLES

List registered providers for the default app:
```bash
php spp.php ai:providers
```

List providers for a specific application context:
```bash
php spp.php ai:providers --app=api
```
