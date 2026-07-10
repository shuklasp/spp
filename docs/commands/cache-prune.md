## `cache:prune`

**Description**: Prune expired cache items from storage

### Synopsis
```bash
php spp.php cache:prune [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CachePruneCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

