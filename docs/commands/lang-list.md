## `lang:list`

**Purpose**: List all translations

### Synopsis
```bash
php spp.php lang:list [OPTIONS]
```

### Options Available
- `--app=` : Expects a value. Extracted via static analysis from LangListCommand.php.

### Under the Hood Activity
Based on static analysis of the command's source code, invoking this command performs the following operations:
- Dynamically loads SPP kernel modules: spplang.
- Bootstraps a full application execution context via Scheduler.

