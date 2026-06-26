# NAME

`view:service:add` - Register a new AJAX service endpoint

# SYNOPSIS

`php spp.php view:service:add --name=<service> --script=<path> [--method=POST] [--app=default] [--source=yaml|db]`

# PURPOSE

Binds a specific backend PHP script to a standardized AJAX API endpoint name, allowing the frontend client to securely interact with backend logic over HTTP.

# OPTIONS AVAILABLE

- `--name=<service>`: The unique identifier name for the AJAX service. (Required)
- `--script=<path>`: The absolute or relative path to the PHP script executing the logic. (Required)
- `--method=<method>`: The allowed HTTP method (`GET`, `POST`, `PUT`, etc.). Defaults to `POST`.
- `--app=<appname>`: The application context to attach the service to. Defaults to `default`.
- `--source=<source>`: The backend storage mapping type (`yaml` or `db`). Defaults to `yaml`.

# UNDER THE HOOD ACTIVITY

The script scans the arguments array to extract configuration flags. It enforces the requirement for both `--name` and `--script`.

Execution context is dynamically swapped via `\SPP\Scheduler::withContext()` to isolate the registration strictly to the requested application domain. Inside this closure, it triggers `\SPPMod\SPPAPI\SPPAjax::registerService($name, $script, $method, $source)`. This static method interacts with the core service registry, securely appending the new AJAX bridge definition to either the YAML topology file or the database routing tables based on the `--source` parameter.

# EXAMPLES

**Register a user login AJAX service:**
```bash
php spp.php view:service:add --name=auth/login --script=Services/LoginService.php
```

**Register a GET service to fetch data:**
```bash
php spp.php view:service:add --name=data/fetch --script=Services/DataFetcher.php --method=GET
```
