## `cron:list`

**Description**: List all registered scheduled tasks

### Synopsis
```bash
php spp.php cron:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CronListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \ReflectionClass.

