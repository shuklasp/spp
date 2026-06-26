## `storage:clean`

**Purpose**: Clean up temporary files in storage

### Synopsis
```bash
php spp.php storage:clean [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from StorageCleanCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Performs direct filesystem modifications (create/write/delete).
- Bootstraps a full application execution context via Scheduler.

