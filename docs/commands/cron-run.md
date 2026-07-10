## `cron:run`

**Description**: Execute pending cron jobs manually

### Synopsis
```bash
php spp.php cron:run [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from CronRunCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: \SPP\CLI\Commands\WorkflowProcessTimeoutsCommand.

