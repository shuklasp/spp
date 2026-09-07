## `lang:list`

**Description**: List all translations

### Synopsis
```bash
php spp.php lang:list [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangListCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).

