# NAME

`view:service:remove` - Remove an AJAX service endpoint from an app

# SYNOPSIS

`php spp.php view:service:remove --name=<service> [--app=default] [--source=yaml|db]`

# PURPOSE

Destroys the routing link mapping an AJAX service endpoint name to its corresponding backend script, disabling access to that service.

# OPTIONS AVAILABLE

- `--name=<service>`: The exact name of the registered AJAX service to unregister. (Required)
- `--app=<appname>`: The application context. Defaults to `default`.
- `--source=<source>`: The original storage medium of the registration (`yaml` or `db`). Defaults to `yaml`.

# UNDER THE HOOD ACTIVITY

After basic CLI argument parsing, the command verifies that the `--name` parameter was provided. It leverages `\SPP\Scheduler::withContext()` to establish the correct operational scope.

Within the context, it delegates the deletion to `\SPPMod\SPPAPI\SPPAjax::unregisterService($name, $source)`. This function is responsible for locating the exact service registry entry within the specified configuration domain (YAML or SQL) and executing a clean, cascading deletion to free up the endpoint namespace.

# EXAMPLES

**Remove the login service:**
```bash
php spp.php view:service:remove --name=auth/login
```
