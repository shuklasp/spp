## `dbsettings:import`

**Purpose**: Import SPP module DB settings from JSON

### Synopsis
```bash
php spp.php dbsettings:import [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php dbsettings:import --file=settings.json [--app=<app_name>]

```

### Options Available
- `--file=` : Expects a value. Extracted via static analysis.
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

