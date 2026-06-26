# NAME

`view:page:list` - List all registered pages/routes for an app

# SYNOPSIS

`php spp.php view:page:list [--app=default]`

# PURPOSE

Provides a comprehensive table view of all currently registered page routes and endpoints mapped within a specified application context.

# OPTIONS AVAILABLE

- `--app=<appname>`: The application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The command parses the `--app` option to determine the context. It executes `\SPPMod\SPPView\Pages::listPages()` inside `\SPP\Scheduler::withContext()` to reliably fetch the aggregated routing table.

If the routing table is empty, it informs the user and exits. Otherwise, it prints a formatted console table header padded via `str_pad()` to ensure clean alignment. As it iterates over the retrieved routes, it intelligently normalizes the structural differences between database-backed routes and YAML-backed routes. For the "Source" column, it resolves the location string by checking the `source` key and falling back to `db_summary` or `source_path`. For the "Target", it extracts the value from `controller`, `url`, or `script` keys.

# EXAMPLES

**List all routes for the default application:**
```bash
php spp.php view:page:list
```

**List all routes for the API application:**
```bash
php spp.php view:page:list --app=api
```
