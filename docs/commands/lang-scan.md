## `lang:scan`

**Description**: Scan directories for new translation keys

### Synopsis
```bash
php spp.php lang:scan [OPTIONS]
```

### Options
- `--app=` : Expects a value. Extracted via static analysis from LangScanCommand.php

### Under the Hood
Based on static analysis of the command's source code:
- Dynamically loads kernel modules: spplang.
- Bootstraps a full application execution context (Scheduler::withContext).
- Instantiates key components: translation.

