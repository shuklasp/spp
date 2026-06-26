# NAME

`view:service:list` - List all registered AJAX services for an app

# SYNOPSIS

`php spp.php view:service:list [--app=default]`

# PURPOSE

Outputs a structured, human-readable overview of all registered AJAX API service bridges actively mapped within a specific application context.

# OPTIONS AVAILABLE

- `--app=<appname>`: The application context. Defaults to `default`.

# UNDER THE HOOD ACTIVITY

The command initiates by determining the target application context from the `--app` option. It then calls `\SPPMod\SPPAPI\SPPAjax::listServices()` wrapped inside a `\SPP\Scheduler::withContext()` block to fetch the raw array of registered services.

If no services exist, a graceful message is printed. Otherwise, a highly structured ASCII table is generated. The command pads the string outputs (`str_pad()`) to align the "Service Name", "Method", "Script", and "Source" columns. Similar to the page lister, it resolves the source column text intelligently by differentiating between database attributes (`db_summary`) and file-based attributes (`source_path`).

# EXAMPLES

**View all AJAX services for the frontend:**
```bash
php spp.php view:service:list
```

**View all AJAX services for a specific app:**
```bash
php spp.php view:service:list --app=backend
```
