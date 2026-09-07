## `view:page:list`

**Description**: List all registered pages/routes for an app

### Synopsis
```bash
php spp.php view:page:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from ViewPageListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

