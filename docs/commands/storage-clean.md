## `storage:clean`

**Description**: Clean up temporary files in storage

### Synopsis
```bash
php spp.php storage:clean [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from StorageCleanCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Bootstraps a full application execution context (Scheduler::withContext).

