## `view:page:list`

**Purpose**: List all registered pages/routes for an app

### Synopsis
```bash
php spp.php view:page:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from ViewPageListCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

