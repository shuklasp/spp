## `cron:flush`

**Description**: Clear cron history and lock files

### Synopsis
```bash
php spp.php cron:flush [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CronFlushCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Performs raw filesystem modifications (create/write/delete).
- Bootstraps a full application execution context (Scheduler::withContext).

