## `drishyam:compile`

**Description**: Pre-compile Drishyam templates for production

### Synopsis
```bash
php spp.php drishyam:compile [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from DrishyamCompileCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

