# dbsettings:export

## NAME
dbsettings:export - Export SPP module DB settings to JSON

## SYNOPSIS
`php spp.php dbsettings:export [--app=<app_name>]`

## PURPOSE
Exports the currently configured database settings mapped by the `DBSettings` module into a structured JSON file format for backups or environment migrations.

## OPTIONS AVAILABLE
- `--app=<app_name>`: Specify the SPP application context (default: 'default').

## UNDER THE HOOD ACTIVITY
The command extracts the application context from the `--app` argument. It then attempts to invoke `\SPP\Scheduler::withContext()` to set the runtime context. Within the closure, it checks whether the `\SPPMod\DBSettings\DBSettings` class is loaded and available. Currently, the actual export logic is a stub and outputs a "Implementation pending" message if the module is active.

## EXAMPLES
```bash
php spp.php dbsettings:export --app=admin
```
