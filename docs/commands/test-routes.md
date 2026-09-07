## `test:routes`

**Description**: Test route scanner

### Synopsis
```bash
php spp.php test:routes [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from TestRouteCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

