## `cron:run`

**Purpose**: Execute pending cron jobs manually

### Synopsis
```bash
php spp.php cron:run [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from CronRunCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

