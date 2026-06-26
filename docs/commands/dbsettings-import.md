# dbsettings:import

## NAME
dbsettings:import - Import SPP module DB settings from JSON

## SYNOPSIS
`php spp.php dbsettings:import --file=<settings.json> [--app=<app_name>]`

## PURPOSE
Imports database configuration overrides and parameters directly from a JSON source file into the SPP DB Settings registry.

## OPTIONS AVAILABLE
- `--file=<path>`: **Required**. Path to the JSON file containing the exported database settings.
- `--app=<app_name>`: Specify the SPP application context (default: 'default').

## UNDER THE HOOD ACTIVITY
The command validates the presence of the `--file` flag. It shifts the application context via `\SPP\Scheduler::withContext()`. Like the export command, it verifies the existence of the `\SPPMod\DBSettings\DBSettings` module. If found, it indicates that the underlying core logic for consuming the JSON file is still an unimplemented stub.

## EXAMPLES
```bash
php spp.php dbsettings:import --file=settings_backup.json --app=default
```
