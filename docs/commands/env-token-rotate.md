## `env:token:rotate`

**Description**: Rotate the system deployment token

### Synopsis
```bash
php spp.php env:token:rotate [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvTokenRotateCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPPMod\SPPXDB\SPP_XDB.

