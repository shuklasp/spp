## `env:status`

**Description**: Display system health and environment status

### Synopsis
```bash
php spp.php env:status [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvStatusCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\SPPDB\SPPDB.

