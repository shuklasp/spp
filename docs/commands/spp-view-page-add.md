# NAME

`view:page:add` - Add a new page route to an app

# SYNOPSIS

`php spp.php view:page:add --name=<route> --url=<target> [--app=default] [--source=yaml|db]`

# PURPOSE

Registers a new frontend page route and maps it to a target controller, script, or template within a specific application context.

# OPTIONS AVAILABLE

- `--name=<route>`: The route identifier or endpoint path (Required).
- `--url=<target>`: The physical target file, controller, or view to map the route to (Required).
- `--app=<appname>`: The application context. Defaults to `default`.
- `--source=<source>`: Where to store the routing configuration. Valid options are `yaml` or `db`. Defaults to `yaml`.

# UNDER THE HOOD ACTIVITY

The command iterates over the CLI arguments to extract the configuration options. If either `--name` or `--url` is missing, it aborts execution and provides usage instructions.

It encapsulates the core logic within `\SPP\Scheduler::withContext()` to ensure the routing change is applied correctly to the selected application domain. Internally, it delegates the heavy lifting to `\SPPMod\SPPView\Pages::savePage($name, $url, $source)`, which handles writing the new routing rule either into a YAML configuration file or persisting it via the database abstraction layer depending on the chosen source mechanism.

# EXAMPLES

**Add a static about page to the default app via YAML:**
```bash
php spp.php view:page:add --name=about --url=views/about.html
```

**Add a database-driven route for the dashboard app:**
```bash
php spp.php view:page:add --name=admin/users --url=Controllers/UserController --app=dashboard --source=db
```
