## `drishyam:theme:check`

**Description**: Validate Drishyam theme assets and structure

### Synopsis
```bash
php spp.php drishyam:theme:check [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from DrishyamThemeCheckCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

