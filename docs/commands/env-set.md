## `env:set`

**Purpose**: Set a specific configuration variable

### Synopsis
```bash
php spp.php env:set [OPTIONS]
```

### Extended Usage
```text
Usage: php spp.php env:set <key> <value> [--app=appname]

```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Bootstraps a full application execution context via Scheduler.

