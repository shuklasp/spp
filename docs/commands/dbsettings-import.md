## `dbsettings:import`

**Description**: Import SPP module DB settings from JSON

### Synopsis
```bash
php spp.php dbsettings:import [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php dbsettings:import --file=settings.json [--app=<app_name>]

```

### Options
- `--file=` : Expects a value. Extracted via static analysis from DBSettingsImportCommand.php
- `--app=` : Expects a value. Extracted via static analysis from DBSettingsImportCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

