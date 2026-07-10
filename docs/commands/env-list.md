## `env:list`

**Description**: List all environment and configuration variables for an app context

### Synopsis
```bash
php spp.php env:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.

