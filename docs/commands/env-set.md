## `env:set`

**Description**: Set a specific configuration variable

### Synopsis
```bash
php spp.php env:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php env:set <key> <value> [--app=appname]

```

### Options
- `--app=` : Expects a value. Extracted via static analysis from EnvSetCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Bootstraps a full application execution context (Scheduler::withContext).

