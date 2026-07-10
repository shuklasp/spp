## `storage:link`

**Description**: Create symbolic links for public storage

### Synopsis
```bash
php spp.php storage:link [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from StorageLinkCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Bootstraps a full application execution context (Scheduler::withContext).

