## `storage:sync`

**Description**: Sync local storage with external disks (stub)

### Synopsis
```bash
php spp.php storage:sync [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from StorageSyncCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

