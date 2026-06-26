# NAME

`view:page:remove` - Remove a page route from an app

# SYNOPSIS

`php spp.php view:page:remove --name=<route> [--app=default] [--source=yaml|db]`

# PURPOSE

Safely unregisters and deletes an existing page route configuration from the specified application context, effectively breaking the mapping between the endpoint and its target resource.

# OPTIONS AVAILABLE

- `--name=<route>`: The route identifier or endpoint path to be removed. (Required)
- `--app=<appname>`: The application context. Defaults to `default`.
- `--source=<source>`: The storage medium where the route currently resides (`yaml` or `db`). Defaults to `yaml`.

# UNDER THE HOOD ACTIVITY

The command parses the passed arguments using simple string matching logic. If the essential `--name` parameter is not present, it outputs usage syntax and terminates early. 

It switches the application environment via `\SPP\Scheduler::withContext()` to guarantee the deletion operates on the correct isolated configuration subset. The removal logic is then dispatched to `\SPPMod\SPPView\Pages::removePage($name, $source)`, which handles the underlying file modification (for YAML sources) or SQL query execution (for database sources) necessary to completely expunge the route. 

# EXAMPLES

**Remove the 'about' route from the YAML configuration:**
```bash
php spp.php view:page:remove --name=about
```

**Remove a dashboard route stored in the database:**
```bash
php spp.php view:page:remove --name=admin/users --app=dashboard --source=db
```
