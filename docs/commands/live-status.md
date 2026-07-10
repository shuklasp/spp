## `live:status`

**Description**: Check the status of websocket/polling servers

### Synopsis
```bash
php spp.php live:status [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LiveStatusCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

