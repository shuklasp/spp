## `view:service:list`

**Description**: List all registered AJAX services for an app

### Synopsis
```bash
php spp.php view:service:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewServiceListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

