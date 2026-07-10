## `env:get`

**Description**: Get a specific configuration variable

### Synopsis
```bash
php spp.php env:get [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php env:get <key> [--app=appname]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvGetCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

