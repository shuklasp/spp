## `live:status`

**Purpose**: Check the status of websocket/polling servers

### Synopsis
```bash
php spp.php live:status [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from LiveStatusCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

